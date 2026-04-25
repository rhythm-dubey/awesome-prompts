<?php

namespace App\Console\Commands;

use App\Helpers\InvoiceHelper;
use App\Mail\OverdueTenantChargeLandlordNotificationMail;
use App\Mail\OverdueTenantChargeReminderMail;
use App\Models\AccountsTenantCharge;
use App\Models\Property;
use App\Models\PropertyLandlord;
use App\Models\Tenancy;
use App\Models\TenancyGuarantors;
use App\Models\TenancyTenants;
use App\Models\TenancyUpdates;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ProcessOverdueTenantCharges extends Command
{
    protected $signature = 'tenant-charges:process-overdue {--tenant-charge-id= : Only process this accounts_tenant_charge.tenant_charge_id (optional)}';

    protected $description = 'Process overdue tenant charges (tenancies from 2019-06-01 onward) and send reminder emails to tenants, guarantors, and landlords';

    public function handle(): int
    {
        try {
            $this->processOverdueTenantCharges();

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('tenant-charges:process-overdue failed', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }

    private function processOverdueTenantCharges(): void
    {
        $today = Carbon::today();

        $query = AccountsTenantCharge::query()
            ->with([
                'tenancy.property.suburb',
                'tenancy.tenants.contact.titleRelation',
                'tenancy.guarantors.contact.primaryEmail',
            ])
            ->whereHas('tenancy', function ($query) {
                $query->where('tenancy_status', 1)
                    ->whereDate('tenancy_start_date', '>=', '2019-06-01')
                    ->where('tenancy_overdue_tc_reminders', 1);
            })
            ->whereRaw('tenant_charge_total_amount_paid != (tenant_charge_total_amount_exc_vat + tenant_charge_total_vat_amount)')
            ->where(function ($query) {
                $query->whereRaw("CURRENT_DATE = (tenant_charge_due_date + INTERVAL '4 days')")
                    ->orWhereRaw("CURRENT_DATE = (tenant_charge_due_date + INTERVAL '7 days')")
                    ->orWhereRaw("CURRENT_DATE = (tenant_charge_due_date + INTERVAL '14 days')")
                    ->orWhereRaw("CURRENT_DATE = (tenant_charge_due_date + INTERVAL '21 days')")
                    ->orWhereRaw("CURRENT_DATE = (tenant_charge_due_date + INTERVAL '28 days')")
                    ->orWhereRaw("CURRENT_DATE = (tenant_charge_due_date + INTERVAL '35 days')")
                    ->orWhereRaw("CURRENT_DATE = (tenant_charge_due_date + INTERVAL '42 days')")
                    ->orWhereRaw("CURRENT_DATE = (tenant_charge_due_date + INTERVAL '49 days')")
                    ->orWhereRaw("CURRENT_DATE = (tenant_charge_due_date + INTERVAL '56 days')")
                    ->orWhereRaw("CURRENT_DATE > (tenant_charge_due_date + INTERVAL '56 days')");
            });

        $tenantChargeIdOption = $this->option('tenant-charge-id');
        if ($tenantChargeIdOption !== null && $tenantChargeIdOption !== '') {
            $onlyId = (int) $tenantChargeIdOption;
            $query->where('tenant_charge_id', $onlyId);
            $this->info('Processing only tenant_charge_id='.$onlyId.' (all other eligibility filters still apply).');
        }

        $query->orderBy('tenant_charge_tenancy_id')
            ->orderBy('tenant_charge_id')
            ->chunkById(100, function ($charges) use ($today) {
                foreach ($charges as $charge) {
                    try {
                        DB::transaction(function () use ($charge, $today): void {
                            $this->processTenantCharge($charge, $today);
                        });
                    } catch (\Throwable $e) {
                        Log::error('tenant-charges:process-overdue transaction failed', [
                            'tenant_charge_id' => $charge->tenant_charge_id,
                            'tenancy_id' => $charge->tenant_charge_tenancy_id,
                            'exception' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                    }
                }
            }, 'tenant_charge_id');
    }

    private function processTenantCharge(AccountsTenantCharge $charge, Carbon $today): void
    {
        $tenancy = $charge->tenancy;
        if (! $tenancy) {
            return;
        }

        $property = $tenancy->property;
        if (! $property) {
            return;
        }

        $overdueDays = $this->dateDiff(
            $charge->tenant_charge_due_date instanceof \DateTimeInterface
                ? $charge->tenant_charge_due_date->format('Y-m-d')
                : (string) $charge->tenant_charge_due_date,
            $today->toDateString()
        );

        if ($tenancy->tenancy_council_pay_rent == 1 && $overdueDays <= 28) {
            return;
        }

        $this->processTenantEmails($charge, $tenancy, $property, $today, $overdueDays);

        $this->processLandlordEmails($charge, $tenancy, $property, $today, $overdueDays);
    }

    private function processTenantEmails(
        AccountsTenantCharge $charge,
        Tenancy $tenancy,
        Property $property,
        Carbon $today,
        int $overdueDays
    ): void {
        $guarantorEmails = $this->getGuarantorEmails($tenancy->tenancy_id);

        $tenants = TenancyTenants::query()
            ->with(['tenant.contact.titleRelation'])
            ->where('tenancy_id', $tenancy->tenancy_id)
            ->whereHas('tenant', function ($query) {
                $dummyEmail = config('saturio.emails.email_dummy_address');
                $query->where('tenant_email_accounts', '!=', $dummyEmail)
                    ->where('tenant_email_accounts', '!=', '')
                    ->whereNotNull('tenant_email_accounts');
            })
            ->orderByDesc('tenant_lead')
            ->get();

        $tenantCount = 0;
        foreach ($tenants as $tenancyTenant) {
            $tenant = $tenancyTenant->tenant;
            if (! $tenant) {
                continue;
            }

            $tenantCount++;

            $tenantEmail = $tenant->tenant_email_accounts;

            $dummyEmail = config('saturio.emails.email_dummy_address');
            if (empty($tenantEmail) || $tenantEmail === $dummyEmail) {
                continue;
            }

            [$subject, $overdueMessage, $overdueDaysForMessage] = $this->getOverdueDetails(
                $charge,
                $property,
                $today,
                $overdueDays
            );

            $alreadyPaidMessage = $this->getAlreadyPaidMessage($today);

            $mailable = new OverdueTenantChargeReminderMail(
                charge: $charge,
                tenancy: $tenancy,
                property: $property,
                tenant: $tenant,
                subject: $subject,
                overdueMessage: $overdueMessage,
                alreadyPaidMessage: $alreadyPaidMessage,
                guarantorEmails: $guarantorEmails
            );

            $shouldCreateTenancyUpdate = $tenantCount == 1 && $overdueDaysForMessage <= 56;

            try {
                if ($shouldCreateTenancyUpdate) {
                    Mail::to($tenantEmail)->send($mailable);
                    $this->createTenancyUpdate($tenancy, $charge, $overdueDaysForMessage);
                } else {
                    Mail::to($tenantEmail)->queue($mailable);
                }

                sleep(1);
            } catch (\Throwable $e) {
                Log::error('tenant-charges:process-overdue tenant email failed', [
                    'tenant_charge_id' => $charge->tenant_charge_id,
                    'tenancy_id' => $tenancy->tenancy_id,
                    'email' => $tenantEmail,
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }
    }

    private function processLandlordEmails(
        AccountsTenantCharge $charge,
        Tenancy $tenancy,
        Property $property,
        Carbon $today,
        int $overdueDays
    ): void {
        $landlords = PropertyLandlord::query()
            ->with(['landlord.contact.titleRelation'])
            ->where('property_id', $property->property_id)
            ->whereHas('landlord', function ($query) {
                $dummyEmail = config('saturio.emails.email_dummy_address');
                $query->where('landlord_emails_tenant_charge_chase', 1)
                    ->where('landlord_email_accounts', '!=', $dummyEmail)
                    ->where('landlord_email_accounts', '!=', '')
                    ->whereNotNull('landlord_email_accounts');
            })
            ->orderByDesc('landlord_lead')
            ->get();

        foreach ($landlords as $propertyLandlord) {
            $landlord = $propertyLandlord->landlord;
            if (! $landlord) {
                continue;
            }

            $landlordEmail = $landlord->landlord_email_accounts;

            $dummyEmail = config('saturio.emails.email_dummy_address');
            if (empty($landlordEmail) || $landlordEmail === $dummyEmail) {
                continue;
            }

            $tenantNames = $this->getTenantNames($tenancy);

            $subject = $this->getLandlordEmailSubject($charge, $property, $today, $overdueDays);

            $alreadyPaidMessage = $this->getAlreadyPaidMessage($today);

            [, $overdueMessage] = $this->getOverdueDetails($charge, $property, $today, $overdueDays);

            try {
                Mail::to($landlordEmail)
                    ->queue(new OverdueTenantChargeLandlordNotificationMail(
                        charge: $charge,
                        tenancy: $tenancy,
                        property: $property,
                        landlord: $landlord,
                        subject: $subject,
                        overdueMessage: $overdueMessage,
                        alreadyPaidMessage: $alreadyPaidMessage,
                        tenantNames: $tenantNames
                    ));

                sleep(1);
            } catch (\Throwable $e) {
                Log::error('tenant-charges:process-overdue landlord email failed', [
                    'tenant_charge_id' => $charge->tenant_charge_id,
                    'tenancy_id' => $tenancy->tenancy_id,
                    'email' => $landlordEmail,
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }
    }

    private function getGuarantorEmails(int $tenancyId): array
    {
        $guarantors = TenancyGuarantors::query()
            ->with(['guarantor.contact.primaryEmail'])
            ->where('tenancy_id', $tenancyId)
            ->whereHas('guarantor', function ($query) {
                $dummyEmail = config('saturio.emails.email_dummy_address');
                $query->whereHas('contact.primaryEmail', function ($emailQ) use ($dummyEmail) {
                    $emailQ->where('email_address', '!=', $dummyEmail)
                        ->where('email_address', '!=', '')
                        ->whereNotNull('email_address');
                });
            })
            ->get();

        $emails = [];
        foreach ($guarantors as $tg) {
            if (! $tg->guarantor) {
                continue;
            }

            $email = $tg->guarantor->contact?->primaryEmail?->email_address;

            if (! empty($email)) {
                $emails[] = $email;
            }
        }

        return $emails;
    }

    private function getTenantNames(Tenancy $tenancy): array
    {
        $tenants = TenancyTenants::query()
            ->with(['tenant.contact.titleRelation'])
            ->where('tenancy_id', $tenancy->tenancy_id)
            ->orderByDesc('tenant_lead')
            ->get();

        $names = [];
        foreach ($tenants as $tt) {
            $tenant = $tt->tenant;
            if (! $tenant) {
                continue;
            }
            $names[] = $tenant->contact?->display_name ?: 'Tenant';
        }

        return $names;
    }

    private function getOverdueDetails(
        AccountsTenantCharge $charge,
        Property $property,
        Carbon $today,
        int $overdueDays
    ): array {
        $dueDate = Carbon::parse($charge->tenant_charge_due_date);
        $propertyAddress = InvoiceHelper::addressFormat(
            $property->property_apartment_number_name,
            $property->property_number_name,
            $property->property_address_line_1,
            $property->property_address_line_2,
            $property->suburb?->suburb_name,
            $property->property_town_city,
            $property->property_postcode,
            ''
        );
        $subjectProperty = ' - '.$propertyAddress;

        $companyName = config('saturio.company.company_name_display');

        $day4 = $dueDate->copy()->addDays(4)->toDateString();
        $day7 = $dueDate->copy()->addDays(7)->toDateString();
        $day14 = $dueDate->copy()->addDays(14)->toDateString();
        $day21 = $dueDate->copy()->addDays(21)->toDateString();
        $day28 = $dueDate->copy()->addDays(28)->toDateString();
        $day35 = $dueDate->copy()->addDays(35)->toDateString();
        $day42 = $dueDate->copy()->addDays(42)->toDateString();
        $day49 = $dueDate->copy()->addDays(49)->toDateString();
        $day56 = $dueDate->copy()->addDays(56)->toDateString();
        $todayStr = $today->toDateString();

        $post2019InterestLegal = "**Interest is currently accruing on this unpaid tenant charge at 3% above the Bank of England base rate, starting from the due date. This interest will continue to accumulate until the charge is paid in full.**\n\n**Please be aware that an overdue tenant charge may result in legal action.**\n\n**If the charge remains unpaid without prior agreement, we may seek a court order for possession of the property and recovery of the outstanding amount. You could also be liable for any court and legal costs incurred.**\n\n**If the court rules in favour of the landlord, a County Court Judgement (CCJ) may be issued against you, which will remain on your credit file for six years. A CCJ can severely impact your ability to secure alternative housing or obtain credit. Additionally, a possession order will be granted, which could lead to eviction. If necessary, court-appointed bailiffs may be instructed to enforce the eviction.**";

        if ($todayStr === $day4) {
            return [
                $companyName.' - Tenant Charge #'.$charge->tenant_charge_id.' - 4 Days Overdue Reminder'.$subjectProperty,
                "**Interest on this unpaid tenant charge is now accruing at 3% above the Bank of England base rate, effective from the charge's due date. This interest will continue to accumulate until the charge is paid in full, though it will not be applied unless the charge remains unpaid for more than 14 days.**\n\n**If the charge remains overdue for an additional three days (totalling seven days overdue), we will proceed with arranging an immediate rent arrears inspection.**",
                4,
            ];
        } elseif ($todayStr === $day7) {
            return [
                $companyName.' - Tenant Charge #'.$charge->tenant_charge_id.' - 7 Days Overdue Reminder'.$subjectProperty,
                "**As this tenant charge is now seven days overdue, we will be arranging an immediate rent arrears property inspection.**\n\n**Interest is accruing on this unpaid charge at 3% above the Bank of England base rate, effective from the due date. This interest will continue to accrue until the charge is settled in full, though it will not be applied unless the charge remains overdue for more than 14 days.**\n\n**Please be aware that failure to pay an overdue tenant charge may result in legal action.**\n\n**If the charge remains unpaid without prior agreement, we may apply to the courts for possession of the property and the outstanding payment. You may also be liable for any associated court and legal costs.**\n\n**If the court rules in favour of the landlord, a County Court Judgement (CCJ) will be issued against you, which will appear on your credit file for six years. This could significantly impact your ability to secure alternative housing or obtain credit. A possession order will also be granted, and, if necessary, court-appointed bailiffs may be instructed to enforce eviction.**",
                7,
            ];
        } elseif ($todayStr === $day14) {
            return [
                $companyName.' - Tenant Charge #'.$charge->tenant_charge_id.' - 14 Days Overdue Reminder'.$subjectProperty,
                "**As this tenant charge is now fourteen days overdue, interest at 3% above the Bank of England base rate will be applied, starting from the original due date, in accordance with your tenancy agreement. Interest will continue to accrue until the full payment is made. You will receive an invoice by email detailing the interest owed and payment instructions. Should the interest invoice remain unpaid, the amount may ultimately be deducted from your deposit.**\n\n**Please be advised that an overdue tenant charge may lead to legal action being taken against you.**\n\n**If the charge remains unpaid without a prior agreement, we may apply to the courts for possession of the property and recovery of the outstanding amount. You may also be held responsible for any associated court and legal costs.**\n\n**If the court rules in favour of the landlord, a County Court Judgement (CCJ) may be issued against you, which will appear on your credit file for six years. This could severely impact your ability to secure alternative accommodation or obtain credit. A possession order may also be granted, leading to eviction. If necessary, court-appointed bailiffs will be instructed to enforce the eviction.**",
                14,
            ];
        } elseif ($todayStr === $day21) {
            return [
                $companyName.' - Tenant Charge #'.$charge->tenant_charge_id.' - 21 Days Overdue Reminder'.$subjectProperty,
                $post2019InterestLegal,
                21,
            ];
        } elseif ($todayStr === $day28) {
            return [
                $companyName.' - Tenant Charge #'.$charge->tenant_charge_id.' - 28 Days Overdue Reminder'.$subjectProperty,
                $post2019InterestLegal,
                28,
            ];
        } elseif ($todayStr === $day35) {
            return [
                $companyName.' - Tenant Charge #'.$charge->tenant_charge_id.' - 5 Weeks Overdue Reminder'.$subjectProperty,
                $post2019InterestLegal,
                35,
            ];
        } elseif ($todayStr === $day42) {
            return [
                $companyName.' - Tenant Charge #'.$charge->tenant_charge_id.' - 6 Weeks Overdue Reminder'.$subjectProperty,
                $post2019InterestLegal,
                42,
            ];
        } elseif ($todayStr === $day49) {
            return [
                $companyName.' - Tenant Charge #'.$charge->tenant_charge_id.' - 7 Weeks Overdue Reminder'.$subjectProperty,
                $post2019InterestLegal,
                49,
            ];
        } elseif ($todayStr === $day56) {
            return [
                $companyName.' - Tenant Charge #'.$charge->tenant_charge_id.' - 8 Weeks Overdue Reminder'.$subjectProperty,
                $post2019InterestLegal,
                56,
            ];
        }

        return [
            $companyName.' - Tenant Charge #'.$charge->tenant_charge_id.' - '.$overdueDays.' Days Overdue Reminder'.$subjectProperty,
            $post2019InterestLegal,
            $overdueDays,
        ];
    }

    private function getLandlordEmailSubject(
        AccountsTenantCharge $charge,
        Property $property,
        Carbon $today,
        int $overdueDays
    ): string {
        $dueDate = Carbon::parse($charge->tenant_charge_due_date);
        $propertyAddress = InvoiceHelper::addressFormat(
            $property->property_apartment_number_name,
            $property->property_number_name,
            $property->property_address_line_1,
            $property->property_address_line_2,
            $property->suburb?->suburb_name,
            $property->property_town_city,
            $property->property_postcode,
            ''
        );
        $subjectProperty = ' - '.$propertyAddress;

        $companyName = config('saturio.company.company_name_display');

        $day4 = $dueDate->copy()->addDays(4)->toDateString();
        $day7 = $dueDate->copy()->addDays(7)->toDateString();
        $day14 = $dueDate->copy()->addDays(14)->toDateString();
        $day21 = $dueDate->copy()->addDays(21)->toDateString();
        $day28 = $dueDate->copy()->addDays(28)->toDateString();
        $day35 = $dueDate->copy()->addDays(35)->toDateString();
        $day42 = $dueDate->copy()->addDays(42)->toDateString();
        $day49 = $dueDate->copy()->addDays(49)->toDateString();
        $day56 = $dueDate->copy()->addDays(56)->toDateString();
        $todayStr = $today->toDateString();

        if ($todayStr === $day4) {
            return $companyName.' - Tenant Charge #'.$charge->tenant_charge_id.' - 4 Days Overdue Reminder'.$subjectProperty;
        } elseif ($todayStr === $day7) {
            return $companyName.' - Tenant Charge #'.$charge->tenant_charge_id.' - 7 Days Overdue Reminder'.$subjectProperty;
        } elseif ($todayStr === $day14) {
            return $companyName.' - Tenant Charge #'.$charge->tenant_charge_id.' - 14 Days Overdue Reminder'.$subjectProperty;
        } elseif ($todayStr === $day21) {
            return $companyName.' - Tenant Charge #'.$charge->tenant_charge_id.' - 21 Days Overdue Reminder'.$subjectProperty;
        } elseif ($todayStr === $day28) {
            return $companyName.' - Tenant Charge #'.$charge->tenant_charge_id.' - 28 Days Overdue Reminder'.$subjectProperty;
        } elseif ($todayStr === $day35) {
            return $companyName.' - Tenant Charge #'.$charge->tenant_charge_id.' - 5 Weeks Overdue Reminder'.$subjectProperty;
        } elseif ($todayStr === $day42) {
            return $companyName.' - Tenant Charge #'.$charge->tenant_charge_id.' - 6 Weeks Overdue Reminder'.$subjectProperty;
        } elseif ($todayStr === $day49) {
            return $companyName.' - Tenant Charge #'.$charge->tenant_charge_id.' - 7 Weeks Overdue Reminder'.$subjectProperty;
        } elseif ($todayStr === $day56) {
            return $companyName.' - Tenant Charge #'.$charge->tenant_charge_id.' - 8 Weeks Overdue Reminder'.$subjectProperty;
        } else {
            return $companyName.' - Tenant Charge #'.$charge->tenant_charge_id.' - '.$overdueDays.' Days Overdue Reminder'.$subjectProperty;
        }
    }

    private function getAlreadyPaidMessage(Carbon $today): string
    {
        $dateStr = $today->format('d/m');
        $christmasDates = config('saturio.system_settings.holiday_christmas_dates', ['24/12', '25/12', '26/12', '28/12', '31/12', '01/01']);
        $easterDates = config('saturio.system_settings.holiday_easter_dates', ['03/04', '04/04', '05/04', '06/04']);

        if (in_array($dateStr, $christmasDates)) {
            return "**If you've already made the payment, kindly disregard this message, and we apologise for any inconvenience. We may not have updated our records yet due to the banks/our office being closed for the Christmas period. If this is the case, you will not be charged a late payment fee and no further action will be taken.**\n\n**However, if payment has not been made, we kindly request that you settle the outstanding amount as soon as possible and ensure all future payments are made in full and on time. Our payment details can be found at the bottom of your tenant charge.**";
        } elseif (in_array($dateStr, $easterDates)) {
            return "**If you've already made the payment, kindly disregard this message, and we apologise for any inconvenience. We may not have updated our records yet due to the banks/our office being closed for the Easter period. If this is the case, you will not be charged a late payment fee and we will not arrange an rent arrears property inspection.**\n\n**However, if payment has not been made, we kindly request that you settle the outstanding amount as soon as possible and ensure all future payments are made in full and on time. Our payment details can be found at the bottom of your tenant charge.**";
        }

        return "If you've already made the payment, kindly disregard this message, and we apologise for any inconvenience.\n\nHowever, if payment has not been made, we kindly request that you settle the outstanding amount as soon as possible and ensure all future payments are made in full and on time. Our payment details can be found at the bottom of your tenant charge.";
    }

    private function createTenancyUpdate(Tenancy $tenancy, AccountsTenantCharge $charge, int $overdueDays): void
    {
        try {
            $totalAmount = $charge->tenant_charge_total_amount_exc_vat + $charge->tenant_charge_total_vat_amount;
            $balanceDue = $totalAmount - $charge->tenant_charge_total_amount_paid;

            $dueDateFormatted = InvoiceHelper::convertDate($charge->tenant_charge_due_date);

            TenancyUpdates::create([
                'tenancy_updates_tenancy_id' => $tenancy->tenancy_id,
                'tenancy_updates_private_notes' => 'Automatic email sent to the tenant(s) requesting that they pay tenant charge #'.$charge->tenant_charge_id.'. The balance due is £'.number_format($balanceDue, 2, '.', ',').', it was due on '.$dueDateFormatted.' and has been outstanding for '.$overdueDays.' days.',
                'tenancy_updates_notify_tenants' => 1,
                'tenancy_updates_notify_landlord' => 1,
                'tenancy_updates_notify_accounts' => 0,
                'tenancy_updates_date_created' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('tenant-charges:process-overdue createTenancyUpdate failed', [
                'tenant_charge_id' => $charge->tenant_charge_id,
                'tenancy_id' => $tenancy->tenancy_id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function dateDiff(string|\DateTimeInterface $start, string $end): int
    {
        $startStr = $start instanceof \DateTimeInterface ? $start->format('Y-m-d') : $start;
        $startTs = strtotime($startStr);
        $endTs = strtotime($end);

        if ($startTs === false || $endTs === false) {
            return 0;
        }

        $diff = $endTs - $startTs;

        return (int) round($diff / 86400);
    }
}
