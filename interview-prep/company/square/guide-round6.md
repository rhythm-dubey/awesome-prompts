## ROUND 6 — AWS Basics (EC2, S3, Deployment, Scaling)

---

### Q1. How do you deploy a Laravel application on AWS EC2?

**Answer:**

**Step-by-step deployment:**

**1. Launch EC2 instance:**
- AMI: Ubuntu 22.04 LTS
- Instance type: t3.medium (for production start)
- Security Group: Open ports 22 (SSH), 80 (HTTP), 443 (HTTPS)

**2. Server setup:**
```bash
sudo apt update && sudo apt upgrade -y
sudo apt install nginx php8.2-fpm php8.2-mysql php8.2-redis php8.2-xml \
    php8.2-curl php8.2-mbstring php8.2-zip composer git supervisor redis-server -y
```

**3. Nginx configuration:**
```nginx
server {
    listen 80;
    server_name myapp.com;
    root /var/www/myapp/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

**4. Deploy application:**
```bash
cd /var/www
git clone git@github.com:user/myapp.git
cd myapp
composer install --optimize-autoloader --no-dev
cp .env.example .env
php artisan key:generate
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan migrate --force
```

**5. Supervisor for queue workers:**
```ini
[program:laravel-worker]
command=php /var/www/myapp/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=4
```

**6. SSL with Certbot:**
```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d myapp.com
```

**Interview Tip:** Walk through the full stack: Nginx -> PHP-FPM -> Laravel -> MySQL/Redis. Mention Supervisor for queues and Certbot for SSL.

---

### Q2. How do you use AWS S3 for file storage in Laravel?

**Answer:**

```bash
composer require league/flysystem-aws-s3-v3
```

```env
AWS_ACCESS_KEY_ID=your-key
AWS_SECRET_ACCESS_KEY=your-secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=my-app-bucket
```

```php
// Upload files
public function upload(Request $request)
{
    $request->validate(['file' => 'required|file|max:10240|mimes:jpg,png,pdf']);

    $path = $request->file('file')->store('uploads/' . auth()->id(), 's3');
    return response()->json(['path' => $path]);
}

// Temporary URL for private files
public function download(Document $document)
{
    $url = Storage::disk('s3')->temporaryUrl(
        $document->path,
        now()->addMinutes(30)
    );
    return response()->json(['url' => $url]);
}
```

**Best practices:**
- Use **presigned URLs** for large file uploads (bypass your server).
- Use **temporary URLs** for private file downloads.
- Set **lifecycle rules** to auto-delete old files.
- Use **CloudFront CDN** in front of S3 for public assets.
- Use **IAM roles** on EC2 instead of access keys when possible.

**Interview Tip:** Mention presigned URLs for direct browser-to-S3 uploads. Also mention CloudFront.

---

### Q3. What is an Application Load Balancer (ALB)? How do you set up Laravel behind it?

**Answer:**

ALB distributes incoming traffic across multiple EC2 instances.

```
Users -> Route 53 (DNS) -> ALB -> EC2 Instance 1
                               -> EC2 Instance 2
                               -> EC2 Instance 3
```

**Laravel configuration for ALB:**

```php
// 1. Trust the load balancer proxy
class TrustProxies extends Middleware
{
    protected $proxies = '*';
}

// 2. Force HTTPS (ALB terminates SSL)
if (app()->isProduction()) {
    URL::forceScheme('https');
}
```

**Critical considerations:**
1. **Session storage** — Use Redis/Database, NOT file sessions.
2. **Cache** — Use Redis, not file cache.
3. **File uploads** — Use S3, not local storage.
4. **Scheduler** — Run on ONE instance only (`onOneServer()`).
5. **Health check endpoint:**

```php
Route::get('/health', function () {
    try {
        DB::connection()->getPdo();
        Cache::store('redis')->get('health-check');
        return response('OK', 200);
    } catch (\Exception $e) {
        return response('Unhealthy', 500);
    }
});
```

**Interview Tip:** The critical answer is about shared state: sessions, cache, and files must use centralized storage when running multiple instances.

---

### Q4. How do you implement CI/CD for a Laravel application?

**Answer:**

**GitHub Actions example:**

```yaml
name: Deploy to Production
on:
  push:
    branches: [main]

jobs:
  test:
    runs-on: ubuntu-latest
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_DATABASE: testing
          MYSQL_ROOT_PASSWORD: password
        ports: ['3306:3306']

    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: mbstring, xml, mysql, redis

      - name: Install Dependencies
        run: composer install --no-interaction --prefer-dist

      - name: Run Tests
        run: php artisan test --parallel

  deploy:
    needs: test
    runs-on: ubuntu-latest
    if: github.ref == 'refs/heads/main'
    steps:
      - name: Deploy to EC2
        uses: appleboy/ssh-action@master
        with:
          host: ${{ secrets.EC2_HOST }}
          username: ${{ secrets.EC2_USER }}
          key: ${{ secrets.SSH_PRIVATE_KEY }}
          script: |
            cd /var/www/myapp
            git pull origin main
            composer install --optimize-autoloader --no-dev
            php artisan migrate --force
            php artisan config:cache
            php artisan route:cache
            php artisan view:cache
            php artisan queue:restart
            sudo systemctl reload php8.2-fpm
```

**Interview Tip:** Mention zero-downtime deployment strategy. Show that you run tests before deploying and restart queue workers.

---

### Q5. How do you handle environment variables and secrets in AWS?

**Answer:**

**AWS Systems Manager Parameter Store (recommended):**
```bash
aws ssm put-parameter --name "/myapp/prod/DB_PASSWORD" --value "secret123" --type "SecureString"
```

**Best practices:**
1. Never store secrets in code or docker images.
2. Use IAM roles on EC2 instead of access key pairs.
3. Parameter Store for most app config (free tier).
4. Secrets Manager for database credentials with auto-rotation.
5. Different secrets per environment.

**Interview Tip:** Say "I use Parameter Store for config and IAM roles for AWS service access."

---

### Q6. How do you set up auto-scaling for a Laravel application on AWS?

**Answer:**

**Architecture:**
```
Route 53 -> ALB -> Auto Scaling Group (min: 2, desired: 2, max: 10)
                     |-- EC2 Instance (AZ-a)
                     |-- EC2 Instance (AZ-b)

RDS Multi-AZ (separate)
ElastiCache Redis (separate)
S3 (file storage)
```

**Scaling policies:**
```
Scale OUT: When average CPU > 70% for 5 minutes -> add 2 instances
Scale IN:  When average CPU < 30% for 10 minutes -> remove 1 instance
```

**Laravel-specific requirements:**
```
SESSION_DRIVER=redis
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
FILESYSTEM_DISK=s3
```

All instances must be stateless — no local sessions, cache, files, or scheduler assumptions.

**Interview Tip:** The key insight: your Laravel app must be completely stateless. Everything shared goes to Redis, S3, or database.

---

### Q7. How do you monitor a Laravel application in production on AWS?

**Answer:**

**Application-level:**
- **Sentry/Bugsnag** — Error tracking with stack traces.
- **Laravel Telescope** — Request/query debugging (staging).
- **Custom health checks** — DB, Redis, S3, Queue status.

**AWS monitoring:**
- **CloudWatch** — CPU, memory, disk metrics. Set alarms.
- **CloudWatch Logs** — Stream Laravel logs.
- **RDS Performance Insights** — Slow query analysis.

**Key metrics:**
```
Application: Response time (p95, p99), error rate, queue depth
Server: CPU, memory, disk I/O
Database: Connections, slow queries, replication lag
Cache: Hit rate, memory usage, evictions
```

**Alerting rules:**
- Error rate > 5% -> Page on-call.
- Response time p95 > 2s -> Warning.
- Queue depth > 1000 -> Scale workers.
- Disk > 85% -> Warning.

**Interview Tip:** Mention Sentry for errors, CloudWatch for infrastructure, and custom health checks. Monitor both application AND infrastructure.

---

### Q8. Explain the difference between RDS and running MySQL on EC2.

**Answer:**

| Feature | RDS | MySQL on EC2 |
|---------|-----|-------------|
| Management | Fully managed | You manage everything |
| Automated backups | Yes, point-in-time recovery | Manual setup |
| Multi-AZ failover | One-click, automatic | Manual replication |
| Read replicas | Easy to create | Manual configuration |
| Cost | Higher (managed premium) | Lower |
| Customization | Limited | Full control |

**Choose RDS for most production Laravel apps.**

**Laravel read replica configuration:**

```php
'mysql' => [
    'read' => [
        'host' => [env('DB_READ_HOST_1'), env('DB_READ_HOST_2')],
    ],
    'write' => [
        'host' => [env('DB_WRITE_HOST')],
    ],
    'sticky' => true, // Reads use write connection after writing
],
```

**Interview Tip:** Say "I use RDS because I don't want to manage backups, patches, and failover manually." Mention the `sticky` option.

---