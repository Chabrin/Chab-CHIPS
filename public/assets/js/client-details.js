/* Client details shortcuts. Record creation stays in the existing forms. */
(function () {
    'use strict';

    var page = document.getElementById('client-details');
    if (!page) return;

    var shortcut = page.querySelector('#openTenantBulkUploadBtn');
    var uploadTab = page.querySelector('#tenants-bulk-upload-subtab');
    var property = page.querySelector('#tenantPropertySelect');
    var addProperty = page.querySelector('#clientCreatedAddProperty');

    if (addProperty) {
        addProperty.addEventListener('click', function () {
            page.querySelector('#properties-tab').click();
            window.bootstrap.Modal.getOrCreateInstance(document.getElementById('addPropertyModal')).show();
        });
    }

    if (shortcut && uploadTab) {
        shortcut.addEventListener('click', function () {
            window.bootstrap.Tab.getOrCreateInstance(uploadTab).show();
            // Leave the selected property intact; prompt for one when needed.
            if (property && !property.value) {
                property.focus();
            } else {
                uploadTab.focus();
            }
        });
    }
})();
