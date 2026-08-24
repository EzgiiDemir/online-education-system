# Process types for PaaS deployment (Heroku/Railway/Render-style platforms
# that read a Procfile natively). For a traditional VPS without Procfile
# support, translate these three lines into systemd units or Supervisor
# programs -- see deploy/supervisor-worker.conf.example for the worker one.
#
# Why this file exists: QUEUE_CONNECTION=database is set in .env, and four
# notification classes (CoursePublished, LiveSessionScheduled,
# TrainingDueSoon, CertificateExpiringSoon) implement ShouldQueue. Without a
# "worker" process actually running queue:work, those jobs sit in the jobs
# table forever and no email is ever sent -- the code is correct, but
# nothing executes it. Same story for the "scheduler" line: the daily
# app:send-training-reminders job (registered in bootstrap/app.php) only
# fires if something is continuously running the scheduler.
web: php artisan serve --host=0.0.0.0 --port=${PORT:-8000}
worker: php artisan queue:work --tries=3 --backoff=30 --max-time=3600 --sleep=3
scheduler: php artisan schedule:work
