<?php

namespace App\Actions;

use Filament\Actions\Concerns\CanOpenModal;
use Filament\Actions\Concerns\InteractsWithRecord;
use Filament\Actions\Contracts\HasRecord;
use Filament\Notifications\Actions\Action;

use Filament\Support\Enums\MaxWidth;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;

class ReplyOnCommentAction extends Action implements HasRecord
{
    use InteractsWithRecord;
    use CanOpenModal;

    public function setRecord(Model $model){
        $this->record($model);

        $this
            ->icon(config('filament-comments.icons.action'))
            ->label(__('filament-comments::filament-comments.comments'))
            ->slideOver()
            ->modalContentFooter(fn (Model $record): View => view('filament-comments::component', [
                'record' => $record,
            ]))
            ->modalHeading(__('filament-comments::filament-comments.modal.heading'))
            ->modalWidth(MaxWidth::Medium)
            ->modalSubmitAction(false)
            ->modalCancelAction(false)
            ->visible(fn (): bool => auth()->user()->can('viewAny', config('filament-comments.comment_model')));
    }
}
