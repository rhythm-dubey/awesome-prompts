# Laravel + PHP Backend Interview Questions and Answers

## Candidate Profile
- Backend Developer using PHP (OOP) and Laravel
- Experience with REST APIs, MVC architecture, SOLID principles
- Experience with MySQL query optimization, indexing, eager loading
- Experience implementing queue systems, cron jobs, background workers
- Experience integrating Stripe payment gateway and third-party APIs
- Familiar with RBAC (Role Based Access Control) architecture
- Uses Git, AWS EC2, Linux, Postman

## Question Categories
1. Laravel Core (10 questions)
2. PHP OOP & Design Principles (8 questions)
3. Database & Query Optimization (8 questions)
4. API Design & Security (6 questions)
5. Queue System & Background Jobs (5 questions)
6. Payment Gateway / Stripe Integration (4 questions)
7. Git & Development Workflow (3 questions)
8. Debugging & Production Issues (3 questions)
9. System Design / Architecture Scenarios (3 questions)

## 1. Laravel Core
### Q1: What is Laravel's service container?
**Answer:**
Laravel's service container is a powerful tool for managing class dependencies and performing dependency injection.
**Example:**
```php
app()->make('SomeClass');
```
---
### Q2: How does middleware work in Laravel?
**Answer:**
Middleware filters HTTP requests entering your application; it can modify or reject requests.
**Example:**
```php
public function handle($request, Closure $next) {
    // Check auth or modify request here
    return $next($request);
}
```
---
// Additional core questions follow similar structure...

## 2. PHP OOP & Design Principles
### Q11: Explain the SOLID principles with examples.
**Answer:**
single responsibility, open/closed principle, Liskov substitution, interface segregation, dependency inversion.
e.g., Using interfaces to depend on abstractions rather than concrete classes.
---
// Continue with other categories similarly...

## 3. Database & Query Optimization
### Q19: How do you optimize MySQL queries in Laravel?
**Answer:** Use eager loading (`with()`), proper indexing, avoid N+1 queries.
e.g., `$users = User::with('posts')->get();`
---
// Continue for remaining categories...

## 4. API Design & Security ... 
and so on for each category.

## Advanced Scenario Questions for Senior Developers:
a) How would you design a scalable notification system using queues?
b) Explain how you would implement multi-tenancy in a Laravel app.
c) Describe strategies to handle database migrations in a live environment without downtime.
d) How do you secure sensitive data stored in your database?
e) Discuss how to optimize performance for high traffic APIs.