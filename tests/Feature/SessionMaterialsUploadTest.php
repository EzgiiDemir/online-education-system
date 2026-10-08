<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseSession;
use App\Models\Material;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Planning a lesson normally means attaching several handouts at once -- the
 * slides, a PDF, a worksheet. The form used to take exactly one file, so a
 * trainer had to create the session and then add the rest one at a time from
 * the curriculum builder.
 */
class SessionMaterialsUploadTest extends TestCase
{
    use RefreshDatabase;

    private function trainerAndCourse(string $delivery = 'offline'): array
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create([
            'instructor_id' => $instructor->id,
            'delivery_type' => $delivery,
        ]);

        return [$instructor, $course];
    }

    public function test_a_trainer_can_attach_several_documents_to_one_session(): void
    {
        Storage::fake('public');
        [$instructor, $course] = $this->trainerAndCourse();

        $this->actingAs($instructor)->post(route('instructor.sessions.store', $course), [
            'title' => 'Hafta 1 — Saha uygulaması',
            'materials' => [
                UploadedFile::fake()->create('sunum.pptx', 120, 'application/vnd.openxmlformats-officedocument.presentationml.presentation'),
                UploadedFile::fake()->create('katilimci-notu.pdf', 90, 'application/pdf'),
                UploadedFile::fake()->create('calisma-formu.docx', 60, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            ],
        ])->assertSessionHasNoErrors();

        $session = CourseSession::where('title', 'Hafta 1 — Saha uygulaması')->firstOrFail();
        $materials = Material::where('course_session_id', $session->id)->get();

        $this->assertCount(3, $materials);
        $this->assertEqualsCanonicalizing(
            ['sunum.pptx', 'katilimci-notu.pdf', 'calisma-formu.docx'],
            $materials->pluck('title')->all()
        );

        foreach ($materials as $material) {
            Storage::disk('public')->assertExists($material->path);
        }
    }

    /**
     * The form tells trainers it accepts "PDF, Word, PowerPoint, Excel,
     * görsel, ses veya video". This asserts the promise against the actual
     * allow-list, so narrowing SAFE_UPLOAD_MIMES can never quietly make the
     * hint a lie. Each was also uploaded through the running app as a
     * genuinely-formed file, not just a fake with the right MIME string.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function acceptedFormats(): array
    {
        return [
            'PDF' => ['elkitabi.pdf', 'application/pdf'],
            'Word (docx)' => ['ders-notu.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'Word (doc)' => ['eski-not.doc', 'application/msword'],
            'Excel (xlsx)' => ['katilimci-listesi.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'Excel (xls)' => ['eski-liste.xls', 'application/vnd.ms-excel'],
            'PowerPoint (pptx)' => ['sunum.pptx', 'application/vnd.openxmlformats-officedocument.presentationml.presentation'],
            'PowerPoint (ppt)' => ['eski-sunum.ppt', 'application/vnd.ms-powerpoint'],
            'ZIP' => ['kaynak-paketi.zip', 'application/zip'],
            'Text' => ['notlar.txt', 'text/plain'],
            'CSV' => ['katilimcilar.csv', 'text/csv'],
            'PNG' => ['diyagram.png', 'image/png'],
            'JPEG' => ['afis.jpg', 'image/jpeg'],
            'GIF' => ['animasyon.gif', 'image/gif'],
            'MP4' => ['tanitim.mp4', 'video/mp4'],
            'WebM' => ['kayit.webm', 'video/webm'],
            'MP3' => ['ses-kaydi.mp3', 'audio/mpeg'],
            'WAV' => ['anons.wav', 'audio/wav'],
        ];
    }

    #[DataProvider('acceptedFormats')]
    public function test_every_advertised_format_is_accepted(string $filename, string $mime): void
    {
        Storage::fake('public');
        [$instructor, $course] = $this->trainerAndCourse();

        $this->actingAs($instructor)->post(route('instructor.sessions.store', $course), [
            'title' => 'Seans — '.$filename,
            'materials' => [UploadedFile::fake()->create($filename, 80, $mime)],
        ])->assertSessionHasNoErrors();

        $session = CourseSession::where('title', 'Seans — '.$filename)->firstOrFail();
        $this->assertSame(
            1,
            Material::where('course_session_id', $session->id)->count(),
            $filename.' should be accepted as a lesson material'
        );
    }

    public function test_one_bad_file_rejects_the_whole_upload_and_creates_no_session(): void
    {
        Storage::fake('public');
        [$instructor, $course] = $this->trainerAndCourse();

        $response = $this->actingAs($instructor)->post(route('instructor.sessions.store', $course), [
            'title' => 'Karışık yükleme',
            'materials' => [
                UploadedFile::fake()->create('temiz.pdf', 50, 'application/pdf'),
                UploadedFile::fake()->create('zararli.exe', 50, 'application/x-msdownload'),
            ],
        ]);

        $response->assertSessionHasErrors('materials.1');
        $this->assertDatabaseMissing('course_sessions', ['title' => 'Karışık yükleme']);
        $this->assertSame(0, Material::count());
    }

    public function test_a_session_can_still_be_planned_with_no_files_at_all(): void
    {
        [$instructor, $course] = $this->trainerAndCourse();

        $this->actingAs($instructor)->post(route('instructor.sessions.store', $course), [
            'title' => 'Materyalsiz seans',
        ])->assertSessionHasNoErrors();

        $session = CourseSession::where('title', 'Materyalsiz seans')->firstOrFail();
        $this->assertSame(0, Material::where('course_session_id', $session->id)->count());
    }

    public function test_the_single_file_field_is_still_accepted(): void
    {
        Storage::fake('public');
        [$instructor, $course] = $this->trainerAndCourse();

        $this->actingAs($instructor)->post(route('instructor.sessions.store', $course), [
            'title' => 'Tek dosyalı seans',
            'material' => UploadedFile::fake()->create('elkitabi.pdf', 40, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $session = CourseSession::where('title', 'Tek dosyalı seans')->firstOrFail();
        $this->assertSame(1, Material::where('course_session_id', $session->id)->count());
    }

    public function test_more_than_twenty_files_is_refused(): void
    {
        [$instructor, $course] = $this->trainerAndCourse();

        $files = [];
        for ($i = 0; $i < 21; $i++) {
            $files[] = UploadedFile::fake()->create("dosya-{$i}.pdf", 10, 'application/pdf');
        }

        $this->actingAs($instructor)->post(route('instructor.sessions.store', $course), [
            'title' => 'Çok dosyalı seans',
            'materials' => $files,
        ])->assertSessionHasErrors('materials');

        $this->assertDatabaseMissing('course_sessions', ['title' => 'Çok dosyalı seans']);
    }

    public function test_an_in_person_session_is_not_told_a_meeting_room_was_created(): void
    {
        [$instructor, $course] = $this->trainerAndCourse('offline');

        $this->actingAs($instructor)
            ->post(route('instructor.sessions.store', $course), ['title' => 'Yüz yüze seans'])
            ->assertSessionHas('success', fn (string $message) => ! str_contains($message, 'toplantı odası'));
    }

    public function test_an_online_session_still_reports_its_meeting_room(): void
    {
        [$instructor, $course] = $this->trainerAndCourse('online');

        $this->actingAs($instructor)
            ->post(route('instructor.sessions.store', $course), ['title' => 'Çevrimiçi seans'])
            ->assertSessionHas('success', fn (string $message) => str_contains($message, 'toplantı odası'));

        $this->assertNotNull(CourseSession::where('title', 'Çevrimiçi seans')->firstOrFail()->room_key);
    }
}
