// HR > Performance Review: the parts of the pages that are plain logic (rows of the lists, the rating boxes, the score shown
// as someone rates). Kept apart from the pages so they can be tested. The real scoring is done on the server; this only
// shows the person what their ratings add up to as they type, with the same rule.
(function (root) {
    'use strict';

    var MIN = 1;
    var MAX = 10;

    // Quotes are escaped too, because names and comments are also written into attributes
    function escapeHtml(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character];
        });
    }

    // Each stage has its own colour; one we do not know is grey and still shows
    var STATUS_TONES = {
        awaiting_self: 'amber',
        awaiting_meeting: 'violet',
        awaiting_supervisor: 'blue',
        awaiting_hr: 'teal',
        finalised: 'green'
    };

    function statusBadge(status, label) {
        return '<span class="pr-chip is-' + (STATUS_TONES[status] || 'grey') + '">' + escapeHtml(label || status) + '</span>';
    }

    // The average of the ratings, each counted by its weight. A criterion nobody rated yet is left out, never counted as zero.
    // who: 'self', 'supervisor' or 'hr'; items: [{ weight, self_rating, supervisor_rating, hr_rating }]
    function scoreOf(items, who) {
        var sum = 0;
        var weights = 0;
        (items || []).forEach(function (item) {
            var rating = item[who + '_rating'];
            var weight = item.weight == null ? 1 : Number(item.weight);
            if (rating === null || rating === undefined || rating === '' || !(weight > 0)) {
                return;
            }
            sum += weight * Number(rating);
            weights += weight;
        });
        return weights > 0 ? Math.round((sum / weights) * 100) / 100 : null;
    }

    // Whether every criterion has a rating, which is what submitting needs
    function isComplete(items, who) {
        if (!items || !items.length) {
            return false;
        }
        return items.every(function (item) {
            var rating = item[who + '_rating'];
            return rating !== null && rating !== undefined && rating !== '';
        });
    }

    function formatScore(score) {
        return score === null || score === undefined ? '-' : Number(score).toFixed(2).replace(/\.?0+$/, '') + ' / ' + MAX;
    }

    // The 1 to 10 drop-down for one criterion
    function ratingSelect(who, itemId, value, disabled) {
        var html = '<select class="form-select form-select-sm pr-rating" data-who="' + escapeHtml(who) + '" data-item="' + escapeHtml(itemId) + '"' + (disabled ? ' disabled' : '') + ' aria-label="Rating from ' + MIN + ' to ' + MAX + '">'
            + '<option value="">-</option>';
        for (var rating = MIN; rating <= MAX; rating++) {
            html += '<option value="' + rating + '"' + (String(value) === String(rating) ? ' selected' : '') + '>' + rating + '</option>';
        }
        return html + '</select>';
    }

    // One row of the My Review, Team Reviews and All Reviews lists. mode: 'my', 'team' or 'all'; url: where the review opens
    function listRowHtml(row, mode, url) {
        var cells = '';
        if (mode !== 'my') {
            cells += '<td><span class="fw-semibold">' + escapeHtml(row.staff) + '</span><div class="small text-muted">' + escapeHtml(row.position) + '</div></td>';
        }
        cells += '<td>' + escapeHtml(row.cycle) + '</td>';
        if (mode === 'all') {
            cells += '<td>' + (row.supervisor ? escapeHtml(row.supervisor) : '<span class="text-muted">' + (row.route === 'hr' ? 'HR rates' : '-') + '</span>') + '</td>';
        }
        var appeal = row.appeal ? '<div class="mt-1"><span class="pr-chip is-' + (row.appeal === 'decided' ? 'grey' : 'amber') + '">' + (row.appeal === 'decided' ? 'Appeal decided' : 'Under appeal') + '</span></div>' : '';
        cells += '<td>' + statusBadge(row.status, row.status_label) + appeal + '</td>';
        cells += '<td class="text-nowrap">' + (row.final_score === null || row.final_score === undefined ? '<span class="text-muted">-</span>' : escapeHtml(formatScore(row.final_score))) + '</td>';
        cells += '<td class="text-nowrap">' + (row.deadline ? escapeHtml(row.deadline) : '<span class="text-muted">-</span>') + '</td>';
        cells += '<td class="text-end"><a class="btn btn-sm btn-outline-primary" href="' + escapeHtml(url) + '">Open</a></td>';
        return '<tr data-id="' + escapeHtml(row.id) + '">' + cells + '</tr>';
    }

    function listBodyHtml(rows, mode, urlFor, emptyText) {
        var columns = mode === 'my' ? 5 : (mode === 'all' ? 7 : 6);
        if (!rows || !rows.length) {
            return '<tr><td colspan="' + columns + '"><div class="pr-empty"><span class="material-symbols-outlined" aria-hidden="true">inbox</span><div class="pr-empty-title">No reviews</div><div>' + escapeHtml(emptyText) + '</div></div></td></tr>';
        }
        return rows.map(function (row) { return listRowHtml(row, mode, urlFor(row)); }).join('');
    }

    root.PerformanceReview = {
        MIN: MIN, MAX: MAX,
        escapeHtml: escapeHtml, statusBadge: statusBadge, scoreOf: scoreOf, isComplete: isComplete, formatScore: formatScore,
        ratingSelect: ratingSelect, listRowHtml: listRowHtml, listBodyHtml: listBodyHtml
    };
})(typeof window !== 'undefined' ? window : this);
