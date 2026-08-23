<?php

namespace Database\Seeders;

use App\Models\AssignmentSubmission;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\CoursePublished;
use App\Notifications\TrainingDueSoon;
use App\Support\DemoSeedTracker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Notification;

/**
 * Populates the portal with static, clearly-tagged showcase data: a second
 * instructor, eight learners with varied departments, two extra courses, and
 * enrollments spread across enrolled/in_progress/completed so every screen
 * (admin lists, instructor dashboard, learner catalog) looks like a portal
 * with real usage instead of one lonely demo account per role.
 *
 * Every row created here is recorded via DemoSeedTracker so it can all be
 * removed in one pass later with `php artisan demo:clear` -- see that
 * command for the removal side of this.
 *
 * Deliberately static (fixed names/emails, no Faker): re-running `db:seed`
 * must not pile up duplicate rows, and the data needs to be recognizable on
 * screen rather than random gibberish.
 */
class DemoFakeDataSeeder extends Seeder
{
    public function run(): void
    {
        $instructor2 = User::updateOrCreate(
            ['email' => 'zeynep.arslan@company.com'],
            ['name' => 'Zeynep Arslan', 'password' => 'password', 'role' => 'instructor', 'department' => 'Eğitim ve Gelişim', 'job_title' => 'Kıdemli Eğitmen']
        );
        DemoSeedTracker::track($instructor2);

        $instructor1 = User::where('email', 'trainer@company.com')->first();
        $manager = User::where('email', 'manager@company.com')->first();

        $learnersData = [
            ['email' => 'elif.yildiz@company.com', 'name' => 'Elif Yıldız', 'department' => 'Satış', 'job_title' => 'Satış Uzmanı', 'reports_to_manager' => true],
            ['email' => 'mehmet.kaya@company.com', 'name' => 'Mehmet Kaya', 'department' => 'Satış', 'job_title' => 'Bölge Sorumlusu', 'reports_to_manager' => true],
            ['email' => 'ayse.sahin@company.com', 'name' => 'Ayşe Şahin', 'department' => 'Pazarlama', 'job_title' => 'Pazarlama Uzmanı', 'reports_to_manager' => true],
            ['email' => 'burak.demir@company.com', 'name' => 'Burak Demir', 'department' => 'Üretim', 'job_title' => 'Vardiya Amiri', 'reports_to_manager' => true],
            ['email' => 'zeynep.celik@company.com', 'name' => 'Zeynep Çelik', 'department' => 'İnsan Kaynakları', 'job_title' => 'İK Uzmanı', 'reports_to_manager' => false],
            ['email' => 'emre.aydin@company.com', 'name' => 'Emre Aydın', 'department' => 'Üretim', 'job_title' => 'Operatör', 'reports_to_manager' => false],
            ['email' => 'selin.koc@company.com', 'name' => 'Selin Koç', 'department' => 'Finans', 'job_title' => 'Finans Uzmanı', 'reports_to_manager' => false],
            ['email' => 'can.ozturk@company.com', 'name' => 'Can Öztürk', 'department' => 'Bilgi Teknolojileri', 'job_title' => 'Yazılım Geliştirici', 'reports_to_manager' => false],
        ];

        $learners = collect($learnersData)->map(function ($data) use ($manager) {
            $learner = User::updateOrCreate(
                ['email' => $data['email']],
                ['name' => $data['name'], 'password' => 'password', 'role' => 'learner', 'department' => $data['department'], 'job_title' => $data['job_title'], 'manager_id' => $data['reports_to_manager'] ? $manager?->id : null]
            );
            DemoSeedTracker::track($learner);

            return $learner;
        });

        $marketing = Course::updateOrCreate(
            ['title' => 'Dijital Pazarlama Temelleri'],
            ['instructor_id' => $instructor2->id, 'description' => 'Sosyal medya, içerik pazarlaması ve temel dijital pazarlama araçlarına giriş.', 'delivery_type' => 'offline', 'status' => 'published', 'category' => 'professional_development', 'starts_at' => now()->subDays(10), 'ends_at' => now()->addDays(20), 'certificate_enabled' => true, 'sdg_goals' => ['4', '8']]
        );
        DemoSeedTracker::track($marketing);
        $marketingSession = $marketing->sessions()->firstOrCreate(['title' => 'Seans 1 - Temel Kavramlar'], ['starts_at' => now()->subDays(9), 'ends_at' => now()->subDays(9)->addHours(2)]);
        DemoSeedTracker::track($marketingSession);
        $marketingSection = $marketing->sections()->firstOrCreate(['title' => 'Giriş'], ['sort_order' => 1]);
        DemoSeedTracker::track($marketingSection);
        $marketingVideo = $marketingSection->activities()->firstOrCreate(['title' => 'Dijital Pazarlamaya Giriş'], ['type' => 'video', 'is_required' => true, 'sort_order' => 1]);
        DemoSeedTracker::track($marketingVideo);

        // Showcases the admin quiz-authoring feature with real, visible content.
        $quiz = $marketingSection->activities()->firstOrCreate(['title' => 'Değerlendirme Sınavı'], ['type' => 'quiz', 'is_required' => true, 'pass_score' => 70, 'sort_order' => 2]);
        DemoSeedTracker::track($quiz);
        if ($quiz->quizQuestions()->count() === 0) {
            $q1 = $quiz->quizQuestions()->create(['question' => 'Aşağıdakilerden hangisi bir dijital pazarlama kanalı değildir?', 'type' => 'single_choice', 'sort_order' => 1]);
            DemoSeedTracker::track($q1);
            $q1->options()->createMany([
                ['label' => 'Sosyal medya reklamları', 'is_correct' => false, 'sort_order' => 1],
                ['label' => 'E-posta pazarlaması', 'is_correct' => false, 'sort_order' => 2],
                ['label' => 'Yüz yüze kapı kapı satış', 'is_correct' => true, 'sort_order' => 3],
            ]);
            $q2 = $quiz->quizQuestions()->create(['question' => 'SEO neyin kısaltmasıdır?', 'type' => 'single_choice', 'sort_order' => 2]);
            DemoSeedTracker::track($q2);
            $q2->options()->createMany([
                ['label' => 'Search Engine Optimization', 'is_correct' => true, 'sort_order' => 1],
                ['label' => 'Social Engagement Outreach', 'is_correct' => false, 'sort_order' => 2],
            ]);
        }

        $leadership = Course::updateOrCreate(
            ['title' => 'Liderlik ve Takım Yönetimi'],
            ['instructor_id' => $instructor1->id, 'description' => 'Canlı sınıfta liderlik teknikleri, geri bildirim ve takım motivasyonu.', 'delivery_type' => 'online', 'status' => 'published', 'category' => 'professional_development', 'starts_at' => now()->addDays(5), 'ends_at' => now()->addDays(5)->addHours(2), 'certificate_enabled' => true, 'sdg_goals' => ['8']]
        );
        DemoSeedTracker::track($leadership);
        $leadershipSession = $leadership->sessions()->firstOrCreate(
            ['title' => 'Canlı Ders - Liderlik Atölyesi'],
            ['starts_at' => now()->addDays(5), 'ends_at' => now()->addDays(5)->addHours(2), 'room_key' => 'demo-liderlik-2026', 'meeting_status' => 'scheduled']
        );
        DemoSeedTracker::track($leadershipSession);
        $leadershipSection = $leadership->sections()->firstOrCreate(['title' => 'Uygulama'], ['sort_order' => 1]);
        DemoSeedTracker::track($leadershipSection);
        $assignment = $leadershipSection->activities()->firstOrCreate(
            ['title' => 'Geri Bildirim Planı Ödevi'],
            ['type' => 'assignment', 'is_required' => true, 'submission_type' => 'text', 'sort_order' => 1]
        );
        DemoSeedTracker::track($assignment);

        $isg = Course::where('title', 'İş Sağlığı ve Güvenliği')->first();
        $iletisim = Course::where('title', 'Etkili İletişim Atölyesi')->first();
        $courses = collect([$isg, $iletisim, $marketing, $leadership])->filter();

        // Deterministic status/course spread so every re-seed produces the
        // exact same picture: three enrolled, three in_progress, two
        // completed (with certificates), fanned out across the four courses.
        $statusCycle = ['enrolled', 'enrolled', 'in_progress', 'in_progress', 'completed', 'in_progress', 'enrolled', 'completed'];

        foreach ($learners as $i => $learner) {
            $course = $courses[$i % $courses->count()];
            $status = $statusCycle[$i % count($statusCycle)];

            $enrollment = Enrollment::firstOrCreate(
                ['user_id' => $learner->id, 'course_id' => $course->id],
                ['status' => $status, 'completed_at' => $status === 'completed' ? now()->subDays($i + 1) : null]
            );
            DemoSeedTracker::track($enrollment);

            if ($status === 'completed' && ! Certificate::where('enrollment_id', $enrollment->id)->exists()) {
                $certificate = Certificate::create([
                    'enrollment_id' => $enrollment->id,
                    'certificate_number' => 'CERT-DEMO-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                    'issued_at' => $enrollment->completed_at ?? now(),
                ]);
                DemoSeedTracker::track($certificate);
            }
        }

        // A pending submission waiting to be graded, so the instructor's
        // assignment queue in the demo isn't empty either.
        $pendingLearner = $learners->firstWhere('email', 'ayse.sahin@company.com');
        $leadershipEnrollment = Enrollment::where('user_id', $pendingLearner->id)->where('course_id', $leadership->id)->first();
        if (! $leadershipEnrollment) {
            $leadershipEnrollment = Enrollment::create(['user_id' => $pendingLearner->id, 'course_id' => $leadership->id, 'status' => 'in_progress']);
            DemoSeedTracker::track($leadershipEnrollment);
        }
        if (! AssignmentSubmission::where('activity_id', $assignment->id)->where('enrollment_id', $leadershipEnrollment->id)->exists()) {
            $submission = AssignmentSubmission::create([
                'activity_id' => $assignment->id,
                'enrollment_id' => $leadershipEnrollment->id,
                'content' => 'Takımım için haftalık bire bir geri bildirim görüşmeleri planladım ve ilk uygulama notlarımı ekliyorum.',
                'submitted_at' => now()->subDay(),
            ]);
            DemoSeedTracker::track($submission);
        }

        // A couple of real, visible notifications so the bell isn't empty.
        // Picked to match the deterministic course assignment above: learner
        // #2 (index 2) lands on $marketing, learner #0 lands on $isg.
        // CoursePublished/TrainingDueSoon implement ShouldQueue, and nothing
        // in this environment runs a queue worker -- Notification::sendNow()
        // bypasses the queue so the demo data is visible immediately instead
        // of silently sitting in the jobs table forever.
        $marketingLearner = $learners->get(2);
        if ($marketingLearner && ! $marketingLearner->notifications()->where('data->course_id', $marketing->id)->exists()) {
            Notification::sendNow($marketingLearner, new CoursePublished($marketing));
            $sent = $marketingLearner->notifications()->where('data->course_id', $marketing->id)->first();
            if ($sent) {
                DemoSeedTracker::track($sent);
            }
        }
        $isgLearner = $learners->get(0);
        $isgEnrollment = $isg ? Enrollment::where('user_id', $isgLearner->id)->where('course_id', $isg->id)->first() : null;
        if ($isgEnrollment && ! $isgLearner->notifications()->where('type', TrainingDueSoon::class)->where('data->course_id', $isg->id)->exists()) {
            Notification::sendNow($isgLearner, new TrainingDueSoon($isgEnrollment, 3));
            $sent = $isgLearner->notifications()->where('type', TrainingDueSoon::class)->where('data->course_id', $isg->id)->first();
            if ($sent) {
                DemoSeedTracker::track($sent);
            }
        }
    }
}
