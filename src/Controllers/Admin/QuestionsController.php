<?php

declare(strict_types=1);

namespace Engelsystem\Controllers\Admin;

use Carbon\Carbon;
use Engelsystem\Controllers\BaseController;
use Engelsystem\Controllers\HasUserNotifications;
use Engelsystem\Controllers\NotificationType;
use Engelsystem\Helpers\Authenticator;
use Engelsystem\Http\Redirector;
use Engelsystem\Http\Request;
use Engelsystem\Http\Response;
use Engelsystem\Models\Question;
use Psr\Log\LoggerInterface;
use Illuminate\Support\Collection;

class QuestionsController extends BaseController
{
    use HasUserNotifications;

    /** @var array<string> */
    protected array $permissions = [
        'question.add',
        'question.edit',
    ];

    public function __construct(
        protected Authenticator $auth,
        protected LoggerInterface $log,
        protected Question $question,
        protected Redirector $redirect,
        protected Response $response
    ) {
    }

    public function index(): Response
    {
        $questions = $this->question
            ->orderBy('answered_at')
            ->orderByDesc('created_at')
            ->get()
            ->load(['user.state', 'answerer.state']);

        /*
         * Leon: To my knowledge we do not have a cronjob,
         * so we run this every time an admin lists all questions.
         */
        $questions = $this->unlockStaleLocks($questions);

        return $this->response->withView(
            'pages/questions/index.twig',
            ['questions' => $questions, 'is_admin' => true]
        );
    }

    /**
     * Unlocks stale locks on the given collection of questions. A lock is considered stale if the
     * editor has been editing the question for over 30 minutes and has not saved it.
     *
     * @param Collection $questions The collection of questions to process.
     * @return Collection The updated collection of questions with stale locks removed.
     */
    public function unlockStaleLocks(Collection $questions): Collection
    {
        $now = Carbon::now();
        return $questions->map(function ($q) use ($now) {
            $started = $q->editing_started_at ?? null;
            if ($q->editor_id && $started instanceof Carbon && $started->copy()->addMinutes(30)->lt($now)) {
                $q->editor()->disassociate();
                $q->editing_started_at = null;
                $q->save();
            }
            return $q;
        });
    }

    public function delete(Request $request): Response
    {
        $data = $this->validate($request, [
            'id'     => 'required|int',
            'delete' => 'checked',
        ]);

        $question = $this->question->findOrFail($data['id']);
        $question->delete();

        $this->log->info('Deleted question {question}', ['question' => $question->text]);
        $this->addNotification('question.delete.success');

        return $this->redirect->to('/admin/questions');
    }

    public function edit(Request $request): Response
    {
        $questionId = (int) $request->getAttribute('question_id');
        $question = $this->question->find($questionId);

        if ($question->editor) {
            if ($question->editor->id !== $this->auth->user()->id) {
                $this->addNotification('question.edit.locked', NotificationType::ERROR);
                return $this->redirect->to('/admin/questions');
            }
        } else {
            $question->editor()->associate($this->auth->user());
            $question->editing_started_at = Carbon::now();
            $question->save();
        }

        return $this->showEdit($question);
    }

    public function save(Request $request): Response
    {
        $questionId = (int) $request->getAttribute('question_id');

        /** @var Question $question */
        $question = $this->question->findOrNew($questionId);

        $data = $this->validate($request, [
            'text'    => 'required',
            'answer'  => 'required',
            'delete'  => 'optional|checked',
            'preview' => 'optional|checked',
        ]);

        if (!is_null($data['delete'])) {
            $question->delete();

            $this->log->info('Deleted question "{question}"', ['question' => $question->text]);

            $this->addNotification('question.delete.success');

            return $this->redirect->to('/admin/questions');
        }

        $question->text = globalCleanText($data['text']);
        $question->answer = globalCleanText($data['answer']);
        $question->answered_at = Carbon::now();
        $question->answerer()->associate($this->auth->user());
        $question->editing_started_at = null;
        $question->editor()->dissociate();

        if (!is_null($data['preview'])) {
            return $this->showEdit($question);
        }

        $question->save();

        $this->log->info(
            'Updated questions "{text}": {answer}',
            ['text' => $question->text, 'answer' => $question->answer]
        );

        $this->addNotification('question.edit.success');

        return $this->redirect->to('/admin/questions');
    }

    public function unlock(Request $request): Response
    {
        $questionId = (int) $request->getAttribute('question_id');
        $question = $this->question->find($questionId);

        if ($question->editor->id !== $this->auth->user()->id) {
            return new Response('', 423);
        }

        $question->editing_started_at = null;
        $question->editor()->dissociate();
        $question->save();

        return new Response('', 200);
    }

    protected function showEdit(?Question $question): Response
    {
        return $this->response->withView(
            'pages/questions/edit.twig',
            ['question' => $question, 'is_admin' => true]
        );
    }
}
