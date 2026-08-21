<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\PortalSetting;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::updateOrCreate(['email' => 'user@company.com'], ['name' => 'Demo Kullanıcı', 'password' => 'password', 'role' => 'learner']);
        $instructor = User::updateOrCreate(['email' => 'trainer@company.com'], ['name' => 'Demo Eğitmen', 'password' => 'password', 'role' => 'instructor']);
        User::updateOrCreate(['email' => 'admin@company.com'], ['name' => 'Demo Yönetici', 'password' => 'password', 'role' => 'admin']);
        PortalSetting::updateOrCreate(['id' => 1], ['portal_name' => 'Arucad Arucad Campus LMS', 'accepted_domains' => ['company.com'], 'local_login_enabled' => true, 'internal_class_enabled' => true, 'entra_enabled' => false, 'default_locale' => 'tr', 'available_locales' => ['tr', 'en']]);

        $offline = Course::updateOrCreate(['title' => 'İş Sağlığı ve Güvenliği'], ['instructor_id' => $instructor->id, 'description' => 'Temel iş güvenliği, acil durum ve kurum prosedürleri.', 'delivery_type' => 'offline', 'status' => 'published', 'starts_at' => now(), 'ends_at' => now()->addDays(14), 'certificate_enabled' => true, 'sdg_goals' => ['3', '4', '8'], 'poster_path' => 'demo/isg-afis.jpg', 'image_path' => 'demo/isg-kapak.jpg']);
        $online = Course::updateOrCreate(['title' => 'Etkili İletişim Atölyesi'], ['instructor_id' => $instructor->id, 'description' => 'Canlı sınıfta iletişim teknikleri ve uygulamalı örnekler.', 'delivery_type' => 'online', 'status' => 'published', 'starts_at' => now()->addDays(2), 'ends_at' => now()->addDays(2)->addHours(2), 'guest_instructor_name' => 'Dr. Ayşe Demir', 'guest_instructor_email' => 'ayse.demir@example.org', 'guest_instructor_bio' => 'İletişim ve yetişkin eğitimi uzmanı.', 'certificate_enabled' => true, 'sdg_goals' => ['4', '5', '10'], 'poster_path' => 'demo/iletisim-afis.jpg', 'image_path' => 'demo/iletisim-kapak.jpg']);
        $session = $offline->sessions()->firstOrCreate(['title' => 'Seans 1 - Temel Güvenlik'], ['starts_at' => now(), 'ends_at' => now()->addHour()]);
        $session->materials()->firstOrCreate(['title' => 'İSG Başlangıç Sunumu.pdf'], ['type' => 'application/pdf', 'path' => 'demo/isg-baslangic-sunumu.pdf', 'duration_seconds' => 900]);
        $live = $online->sessions()->updateOrCreate(['title' => 'Canlı Ders - İletişim Temelleri'], ['starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHours(2), 'room_key' => 'demo-iletisim-2026', 'meeting_status' => 'scheduled']);
        $live->materials()->firstOrCreate(['title' => 'Atölye Slaytları.pptx'], ['type' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'path' => 'demo/iletisim-atolyesi.pptx']);
        Enrollment::firstOrCreate(['user_id' => $user->id, 'course_id' => $offline->id]);
        Enrollment::firstOrCreate(['user_id' => $user->id, 'course_id' => $online->id]);
    }
}
