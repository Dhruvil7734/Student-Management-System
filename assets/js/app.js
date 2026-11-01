/* Student Management System — UI interactions */
(function () {
    'use strict';

    // ---------------------------------------------------- sidebar (mobile)
    var body = document.body;
    var toggle = document.getElementById('sidebarToggle');
    var backdrop = document.getElementById('sidebarBackdrop');

    if (toggle) {
        toggle.addEventListener('click', function () {
            body.classList.toggle('sidebar-open');
        });
    }
    if (backdrop) {
        backdrop.addEventListener('click', function () {
            body.classList.remove('sidebar-open');
        });
    }

    // ---------------------------------------------------- confirm actions
    document.addEventListener('click', function (event) {
        var el = event.target.closest('[data-confirm]');
        if (!el) return;
        if (!window.confirm(el.getAttribute('data-confirm'))) {
            event.preventDefault();
            event.stopPropagation();
        }
    });

    // ---------------------------------------------------- image preview
    document.querySelectorAll('[data-preview-target]').forEach(function (input) {
        input.addEventListener('change', function () {
            var target = document.querySelector(input.getAttribute('data-preview-target'));
            if (target && input.files && input.files[0]) {
                target.src = URL.createObjectURL(input.files[0]);
                target.classList.remove('d-none');
                var fallback = document.getElementById('photoFallback');
                if (fallback) fallback.classList.add('d-none');
            }
        });
    });

    // ---------------------------------------------------- attendance helpers
    var markAll = document.getElementById('markAllPresent');
    if (markAll) {
        markAll.addEventListener('click', function () {
            document.querySelectorAll('.att-status input[value="Present"]').forEach(function (radio) {
                radio.checked = true;
            });
        });
    }

    var selectAll = document.getElementById('selectAllStudents');
    if (selectAll) {
        selectAll.addEventListener('change', function () {
            document.querySelectorAll('.student-check').forEach(function (cb) {
                cb.checked = selectAll.checked;
            });
        });
    }

    // ---------------------------------------------------- auto dismiss alerts
    document.querySelectorAll('.alert-dismissible').forEach(function (alert) {
        window.setTimeout(function () {
            if (alert && alert.classList.contains('show')) {
                var instance = bootstrap.Alert.getOrCreateInstance(alert);
                instance.close();
            }
        }, 6000);
    });

    // ---------------------------------------------------- server-computed charts helper
    window.smsChart = function (canvasId, config) {
        var canvas = document.getElementById(canvasId);
        if (!canvas || typeof Chart === 'undefined') return null;
        return new Chart(canvas, config);
    };
})();
