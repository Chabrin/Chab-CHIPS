// Add Staff: every position has its system role, so there is no Role box to choose from. Choosing a position shows the role the login
// will get (read only). The server decides the role from the position when the form is saved; this only tells HR which one it is.
(function (root) {
    'use strict';

    // The role name a position option carries, or '' when it has none
    function roleNameOf(option) {
        return option ? String(option.getAttribute('data-role-name') || '').trim() : '';
    }

    function attach(doc) {
        var position = doc.getElementById('position_id');
        var display = doc.getElementById('role_display');
        if (!position || !display) return null;
        var note = doc.getElementById('roleNote');

        function selectedOption() {
            return position.options && position.selectedIndex >= 0 ? position.options[position.selectedIndex] : null;
        }

        function apply() {
            var option = selectedOption();
            var chosen = !!option && String(option.value || '') !== '';
            var name = roleNameOf(option);

            display.value = name;
            if (!note) return;
            if (!chosen) note.textContent = 'Set by the position.';
            else if (name) note.textContent = 'Set by the position. Choose another position to change it.';
            else note.textContent = 'This position has no role yet. One is made for it when the staff member is saved, with no access until it is given menus.';
        }

        position.addEventListener('change', apply);
        apply();

        current = { apply: apply };
        return current;
    }

    // jQuery's trigger('change') does not reach a listener added this way, so the page calls refresh() after it rebuilds the position list
    var current = null;
    function refresh() {
        if (current) current.apply();
    }

    root.PositionRole = { roleNameOf: roleNameOf, attach: attach, refresh: refresh };

    if (root.document) {
        if (root.document.readyState === 'loading') {
            root.document.addEventListener('DOMContentLoaded', function () { attach(root.document); });
        } else {
            attach(root.document);
        }
    }
})(typeof window !== 'undefined' ? window : this);
