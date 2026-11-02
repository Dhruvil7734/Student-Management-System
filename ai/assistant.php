<?php
/**
 * AI Assistant page.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/openai.php';

$totalStudents = table_count('students');
$activeStudents = table_count('students', "status = 'Active'");
$totalTeachers = table_count('teachers');
$totalCourses  = table_count('courses');
$totalSubjects = table_count('subjects');

$collected = (float) db()->query('SELECT COALESCE(SUM(amount),0) FROM fees')->fetchColumn();
$expected  = (float) db()->query(
    "SELECT COALESCE(SUM(c.total_fee),0) FROM students s JOIN courses c ON c.id = s.course_id WHERE s.status = 'Active'"
)->fetchColumn();
$pending = max(0, $expected - $collected);

$stmt = db()->prepare(
    'SELECT COUNT(*) total, SUM(status = "Present") present, SUM(status = "Absent") absent, SUM(status = "Late") late
     FROM attendance WHERE attendance_date = CURDATE()'
);
$stmt->execute();
$today = $stmt->fetch() ?: ['total' => 0, 'present' => 0, 'absent' => 0, 'late' => 0];

$attendancePct = (int) $today['total'] > 0
    ? round(((int) $today['present'] + (int) $today['late']) / (int) $today['total'] * 100, 1)
    : 0;

$page_title    = 'AI Assistant';
$page_subtitle = 'Ask questions and generate summaries from your institution data';
$active        = 'assistant';
require dirname(__DIR__) . '/includes/header.php';
?>

<?php if (!openai_is_configured()): ?>
    <div class="alert alert-warning shadow-sm">
        <div class="d-flex gap-3">
            <i class="bi bi-key-fill fs-4"></i>
            <div>
                <strong>OpenAI is not configured.</strong>
                <p class="mb-2 small">Add your API key on the server to enable the assistant. Create <code>config/local.php</code> with:</p>
                <pre class="small bg-white border rounded p-2 mb-2" style="white-space:pre-wrap;">&lt;?php define('OPENAI_API_KEY', 'sk-your-key-here');</pre>
                <p class="mb-0 small text-muted">You can also set the <code>OPENAI_API_KEY</code> environment variable. Keys are never sent to the browser.</p>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-stars me-2 text-primary"></i>Campus AI</span>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge <?= openai_is_configured() ? 'text-bg-success' : 'text-bg-secondary' ?> badge-soft">
                        <?= openai_is_configured() ? 'Connected' : 'Not configured' ?>
                    </span>
                    <button class="btn btn-sm btn-soft" type="button" id="clearChat">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Clear
                    </button>
                </div>
            </div>

            <div class="chat-panel">
                <div class="chat-messages" id="chatMessages">
                    <div class="chat-msg assistant">
                        <span class="chat-avatar"><i class="bi bi-stars"></i></span>
                        <div class="bubble">
Hello! I can help you understand your institution's data, summarise academic performance,
                            explain attendance trends, and draft notices or reports.

Try one of the suggestions below, or ask me anything about the Student Management System.
                        </div>
                    </div>
                </div>

                <div class="chat-input">
                    <div class="d-flex flex-wrap gap-2 mb-2" id="quickPrompts">
                        <button class="btn btn-sm btn-soft" type="button" data-prompt="Summarise the institution's current performance using the dashboard statistics.">Summarise performance</button>
                        <button class="btn btn-sm btn-soft" type="button" data-prompt="Explain today's attendance trends and suggest actions if attendance is low.">Attendance insights</button>
                        <button class="btn btn-sm btn-soft" type="button" data-prompt="Draft a polite fee reminder notice for students with pending fees.">Draft fee reminder</button>
                        <button class="btn btn-sm btn-soft" type="button" data-prompt="Give me a short guide on how to mark attendance and record exam results in this system.">How-to guide</button>
                    </div>
                    <form id="chatForm" class="d-flex gap-2 align-items-end">
                        <textarea class="form-control" id="chatInput" rows="1" placeholder="Ask the assistant…"
                                  style="resize:none;max-height:120px;" <?= openai_is_configured() ? '' : 'disabled' ?>></textarea>
                        <button class="btn btn-primary" type="submit" id="chatSend" <?= openai_is_configured() ? '' : 'disabled' ?>>
                            <i class="bi bi-send"></i>
                        </button>
                    </form>
                    <div class="form-text mt-1">AI responses may be inaccurate. Review important information before acting on it.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-database me-2 text-primary"></i>Data the assistant can see</div>
            <div class="card-body">
                <ul class="info-list">
                    <li><span class="label">Students</span><span class="value"><?= number_format($totalStudents) ?> (<?= number_format($activeStudents) ?> active)</span></li>
                    <li><span class="label">Teachers</span><span class="value"><?= number_format($totalTeachers) ?></span></li>
                    <li><span class="label">Courses / subjects</span><span class="value"><?= number_format($totalCourses) ?> / <?= number_format($totalSubjects) ?></span></li>
                    <li><span class="label">Fees collected</span><span class="value"><?= short_money($collected) ?></span></li>
                    <li><span class="label">Pending fees</span><span class="value text-danger"><?= short_money($pending) ?></span></li>
                    <li><span class="label">Today's attendance</span><span class="value"><?= $attendancePct ?>%</span></li>
                </ul>
                <p class="text-muted small mb-0 mt-3">
                    Only aggregate statistics are shared automatically. Avoid pasting sensitive personal data into the chat.
                </p>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="bi bi-lightbulb me-2 text-warning"></i>Good prompts</div>
            <div class="card-body text-muted small">
                <ul class="mb-0 ps-3">
                    <li class="mb-2">“Which course has the most students and why might that be?”</li>
                    <li class="mb-2">“Write a short academic performance summary for the management meeting.”</li>
                    <li class="mb-2">“Explain how pending fees are calculated.”</li>
                    <li>“What should I check before publishing exam results?”</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var form = document.getElementById('chatForm');
    var input = document.getElementById('chatInput');
    var messages = document.getElementById('chatMessages');
    var send = document.getElementById('chatSend');
    var clear = document.getElementById('clearChat');
    var token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var history = [];
    var busy = false;

    if (!form) return;

    function scrollDown() {
        messages.scrollTop = messages.scrollHeight;
    }

    function addMessage(role, text) {
        var wrap = document.createElement('div');
        wrap.className = 'chat-msg ' + role;

        var avatar = document.createElement('span');
        avatar.className = 'chat-avatar';
        avatar.innerHTML = role === 'user' ? '<i class="bi bi-person"></i>' : '<i class="bi bi-stars"></i>';

        var bubble = document.createElement('div');
        bubble.className = 'bubble';
        bubble.textContent = text;

        wrap.appendChild(avatar);
        wrap.appendChild(bubble);
        messages.appendChild(wrap);
        scrollDown();
        return bubble;
    }

    function addTyping() {
        var wrap = document.createElement('div');
        wrap.className = 'chat-msg assistant';
        wrap.id = 'typingIndicator';
        wrap.innerHTML = '<span class="chat-avatar"><i class="bi bi-stars"></i></span>'
            + '<div class="bubble"><span class="typing"><span></span><span></span><span></span></span></div>';
        messages.appendChild(wrap);
        scrollDown();
        return wrap;
    }

    input.addEventListener('input', function () {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 120) + 'px';
    });

    input.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            form.dispatchEvent(new Event('submit', { cancelable: true }));
        }
    });

    document.querySelectorAll('#quickPrompts [data-prompt]').forEach(function (button) {
        button.addEventListener('click', function () {
            input.value = button.getAttribute('data-prompt');
            form.dispatchEvent(new Event('submit', { cancelable: true }));
        });
    });

    clear.addEventListener('click', function () {
        history = [];
        messages.querySelectorAll('.chat-msg').forEach(function (node) {
            if (!node.id || node.id !== 'welcome') {
                if (node.id !== 'typingIndicator') node.remove();
            }
        });
        messages.innerHTML = '';
        addMessage('assistant', 'New conversation started. What would you like to know?');
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        if (busy) return;

        var text = input.value.trim();
        if (!text) return;

        busy = true;
        send.disabled = true;
        addMessage('user', text);
        history.push({ role: 'user', content: text });
        input.value = '';
        input.style.height = 'auto';

        var typing = addTyping();

        fetch('<?= e(url('ai/chat.php')) ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': token
            },
            body: JSON.stringify({ messages: history, csrf_token: token })
        })
        .then(function (response) {
            return response.json().catch(function () {
                return { ok: false, error: 'Unexpected server response.' };
            });
        })
        .then(function (data) {
            if (typing && typing.parentNode) typing.remove();
            if (data.ok) {
                addMessage('assistant', data.content);
                history.push({ role: 'assistant', content: data.content });
            } else {
                addMessage('assistant', '⚠ ' + (data.error || 'Something went wrong. Please try again.'));
            }
        })
        .catch(function () {
            if (typing && typing.parentNode) typing.remove();
            addMessage('assistant', '⚠ Network error. Please check your connection and try again.');
        })
        .finally(function () {
            busy = false;
            send.disabled = false;
            input.focus();
        });
    });
})();
</script>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
