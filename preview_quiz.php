<?php
require_once(__DIR__ . '/../../config.php');
require_login();

global $DB, $PAGE, $OUTPUT;

$id = required_param('id', PARAM_INT);
$record = $DB->get_record('ai_generated_quiz', ['id' => $id], '*', MUST_EXIST);

$PAGE->set_url(new moodle_url('/local/ai_assistant/preview_quiz.php', ['id' => $id]));
$PAGE->set_context(context_system::instance());
$PAGE->set_title("Preview Quiz AI");
$PAGE->set_heading("Preview Quiz AI");

$gift = $record->quiz_data;

function parse_gift($text) {
    $questions = [];
    $blocks = preg_split('/\n\s*\n/', trim($text));

    foreach ($blocks as $block) {
        if (preg_match('/::(.+?)::(.*?){(.*)}/s', $block, $m)) {
            $title = trim($m[1]);
            $question = trim($m[2]);
            $answers_raw = trim($m[3]);

            $answers = [];
            $lines = preg_split('/[\r\n]+/', $answers_raw);

            foreach ($lines as $line) {
                $line = trim($line);
                if (!$line) continue;

                $correct = false;
                if (strpos($line, '=') === 0) {
                    $correct = true;
                    $line = substr($line, 1);
                } else if (strpos($line, '~') === 0) {
                    $line = substr($line, 1);
                }

                $answers[] = [
                    'text' => trim($line),
                    'correct' => $correct
                ];
            }

            $questions[] = [
                'title' => $title,
                'question' => $question,
                'answers' => $answers
            ];
        }
    }
    return $questions;
}

$questions = parse_gift($gift);

echo $OUTPUT->header();
?>

<style>
.quiz-preview-card {
    border: 1px solid #ccc;
    padding: 15px;
    margin-bottom: 25px;
    border-radius: 6px;
    background: #fafafa;
}
.answer {
    padding: 6px 12px;
    margin: 4px 0;
    border-radius: 4px;
    display: block;
    width: fit-content;
}
.answer.correct {
    background: #d4edda;
    color: #155724;
    font-weight: bold;
}
.answer.incorrect {
    background: #f8d7da;
    color: #721c24;
}
.question-title {
    font-weight: bold;
    font-size: 18px;
    margin-bottom: 6px;
}
.question-text {
    margin-bottom: 12px;
}
.preview-header {
    margin-bottom: 20px;
    padding: 15px;
    background: #eef5ff;
    border-radius: 6px;
}
</style>

<div class="preview-header">
    <h2>Preview Quiz dari AI</h2>
    <p><strong>ID Generate:</strong> <?= $record->id ?></p>
    <p><strong>Jumlah Soal:</strong> <?= $record->question_count ?></p>
    <p><strong>Kesulitan:</strong> <?= ucfirst($record->difficulty) ?></p>
</div>

<div style="margin-bottom: 30px;">
    <a class="btn btn-primary"
       href="<?= new moodle_url('/local/ai_assistant/import_quiz_action.php', [
            'quizid' => $record->id,
            'sesskey' => sesskey()
       ]) ?>">
        Import ke Bank Soal Moodle
    </a>
</div>

<?php foreach ($questions as $q): ?>
<div class="quiz-preview-card">
    <div class="question-title">
        <?= htmlspecialchars($q['title']) ?>
    </div>
    <div class="question-text">
        <?= nl2br(htmlspecialchars($q['question'])) ?>
    </div>
    <?php foreach ($q['answers'] as $ans): ?>
        <div class="answer <?= $ans['correct'] ? 'correct' : 'incorrect' ?>">
            <?= $ans['correct'] ? '✔' : '✖' ?>  
            <?= htmlspecialchars($ans['text']) ?>
        </div>
    <?php endforeach; ?>
</div>
<?php endforeach; ?>

<?php
echo $OUTPUT->footer();
?>