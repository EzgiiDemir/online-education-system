<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\DemoSeedRecord;
use App\Models\Enrollment;
use App\Models\User;
use Database\Seeders\DemoFakeDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * DemoFakeDataSeeder exists purely to make admin/instructor/learner screens
 * look like a portal with real usage for a showcase, and it must be safe to
 * run repeatedly (re-seeding) and fully reversible (demo:clear) without
 * touching any of the real base data it depends on -- see [[demo-fake-data]]
 * memory for the "static, easy to remove" requirement this exists to meet.
 */
class DemoFakeDataSeederTest extends TestCase
{
    use RefreshDatabase;

    private function seedBaseFixtures(): void
    {
        $instructor = User::updateOrCreate(['email' => 'trainer@company.com'], ['name' => 'Demo Eğitmen', 'password' => 'password', 'role' => 'instructor']);
        User::updateOrCreate(['email' => 'manager@company.com'], ['name' => 'Demo Departman Yöneticisi', 'password' => 'password', 'role' => 'manager']);
        Course::updateOrCreate(['title' => 'İş Sağlığı ve Güvenliği'], ['instructor_id' => $instructor->id, 'delivery_type' => 'offline', 'status' => 'published']);
        Course::updateOrCreate(['title' => 'Etkili İletişim Atölyesi'], ['instructor_id' => $instructor->id, 'delivery_type' => 'online', 'status' => 'published']);
    }

    public function test_seeder_creates_fake_learners_courses_and_enrollments(): void
    {
        Notification::fake();
        $this->seedBaseFixtures();

        (new DemoFakeDataSeeder)->run();

        $this->assertDatabaseHas('users', ['email' => 'elif.yildiz@company.com', 'role' => 'learner']);
        $this->assertDatabaseHas('users', ['email' => 'zeynep.arslan@company.com', 'role' => 'instructor']);
        $this->assertDatabaseHas('courses', ['title' => 'Dijital Pazarlama Temelleri']);
        $this->assertDatabaseHas('courses', ['title' => 'Liderlik ve Takım Yönetimi']);
        $this->assertGreaterThan(0, Enrollment::count());
        $this->assertGreaterThan(0, DemoSeedRecord::count());
    }

    public function test_seeder_is_idempotent_and_does_not_duplicate_on_a_second_run(): void
    {
        Notification::fake();
        $this->seedBaseFixtures();

        (new DemoFakeDataSeeder)->run();
        $usersAfterFirst = User::count();
        $enrollmentsAfterFirst = Enrollment::count();
        $recordsAfterFirst = DemoSeedRecord::count();

        (new DemoFakeDataSeeder)->run();

        $this->assertSame($usersAfterFirst, User::count());
        $this->assertSame($enrollmentsAfterFirst, Enrollment::count());
        $this->assertSame($recordsAfterFirst, DemoSeedRecord::count());
    }

    public function test_demo_clear_removes_only_the_tracked_fake_rows(): void
    {
        Notification::fake();
        $this->seedBaseFixtures();
        $realCourseCount = Course::count();
        $realUserCount = User::count();

        (new DemoFakeDataSeeder)->run();
        $this->assertGreaterThan($realCourseCount, Course::count());

        Artisan::call('demo:clear', ['--force' => true]);

        $this->assertSame($realCourseCount, Course::count());
        $this->assertSame($realUserCount, User::count());
        $this->assertSame(0, DemoSeedRecord::count());
        // The real base courses must survive the clear untouched.
        $this->assertDatabaseHas('courses', ['title' => 'İş Sağlığı ve Güvenliği']);
        $this->assertDatabaseMissing('courses', ['title' => 'Dijital Pazarlama Temelleri']);
    }

    public function test_demo_clear_reports_when_there_is_nothing_to_remove(): void
    {
        $exitCode = Artisan::call('demo:clear', ['--force' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('yok', Artisan::output());
    }
}
