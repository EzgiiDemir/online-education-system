<?php

namespace App\Filament\Actions;

use App\Models\Activity;
use App\Services\QuizAnalyticsService;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

/**
 * Instructor-facing "how is this quiz actually performing" modal -- pass
 * rate, average score, and a per-question correct-rate breakdown, computed
 * on demand by QuizAnalyticsService. Only meaningful for quiz activities.
 */
class QuizAnalyticsAction
{
    public static function make(): Action
    {
        return Action::make('quizAnalytics')
            ->label(__('Analitik'))
            ->icon(Heroicon::OutlinedChartBar)
            ->color('gray')
            ->visible(fn (Activity $record) => $record->type === 'quiz')
            ->modalHeading(__('Sınav analitiği'))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('Kapat'))
            ->modalContent(fn (Activity $record) => view('filament.quiz-analytics-modal', [
                'summary' => app(QuizAnalyticsService::class)->summarize($record),
            ]));
    }
}
