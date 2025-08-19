jQuery(document).ready(function($) {
    'use strict';

    // Cache DOM elements
    const $userFilter = $('#user-filter');
    const $courseFilter = $('#course-filter');
    const $filterButton = $('#filter-button');
    const $resetButton = $('#reset-filters');
    const $userCoursesList = $('#user-courses-list');
    const $setExpirationDialog = $('#set-expiration-dialog');
    const $expirationForm = $('#set-expiration-form');
    const $expirationType = $('#expiration-type');
    const $customDateRow = $('#custom-date-row');
    const $customDate = $('#custom-date');
    const $expirationPreview = $('#expiration-preview-text');
    
    // Initialize dialog
    const setExpirationDialog = $setExpirationDialog.dialog({
        autoOpen: false,
        modal: true,
        width: 600,
        dialogClass: 'wp-dialog',
        closeOnEscape: true,
        close: function() {
            $expirationForm.trigger('reset');
            $customDateRow.hide();
        }
    });
    
    // Load initial data
    loadUserCourses();
    
    // Event listeners
    $filterButton.on('click', loadUserCourses);
    $resetButton.on('click', resetFilters);
    
    $expirationType.on('change', function() {
        if ($(this).val() === 'custom') {
            $customDateRow.show();
        } else {
            $customDateRow.hide();
        }
        updateExpirationPreview();
    });
    
    $customDate.on('change', updateExpirationPreview);
    
    $expirationForm.on('submit', function(e) {
        e.preventDefault();
        saveExpiration();
    });
    
    $('.cancel-button').on('click', function() {
        setExpirationDialog.dialog('close');
    });
    
    // Handle dynamic elements
    $(document).on('click', '.set-expiration-button', function() {
        const userId = $(this).data('user-id');
        const courseId = $(this).data('course-id');
        const expires = $(this).data('expires');
        
        openExpirationDialog(userId, courseId, expires);
    });
    
    // Functions
    function loadUserCourses() {
        const userId = $userFilter.val() || '';
        const courseId = $courseFilter.val() || '';
        
        $userCoursesList.html(`
            <tr>
                <td colspan="4" class="loading">
                    <span class="spinner is-active"></span>
                    ${lilacCourseAccess.i18n.loading}
                </td>
            </tr>
        `);
        
        $.ajax({
            url: lilacCourseAccess.ajax_url,
            type: 'GET',
            data: {
                action: 'lilac_get_user_courses',
                user_id: userId,
                course_id: courseId,
                nonce: lilacCourseAccess.nonce
            },
            success: function(response) {
                if (response.success) {
                    renderUserCourses(response.data);
                } else {
                    showError(response.data);
                }
            },
            error: function() {
                showError(lilacCourseAccess.i18n.error_loading);
            }
        });
    }
    
    function renderUserCourses(users) {
        if (!users || users.length === 0) {
            $userCoursesList.html(`
                <tr>
                    <td colspan="4" class="no-results">
                        ${lilacCourseAccess.i18n.no_results}
                    </td>
                </tr>
            `);
            return;
        }
        
        let html = '';
        
        users.forEach(function(user) {
            html += `
                <tr>
                    <td><strong>${escapeHtml(user.display_name)}</strong></td>
                    <td>${escapeHtml(user.user_email)}</td>
                    <td>
                        ${user.courses && user.courses.length > 0 
                            ? user.courses.map(course => `
                                <div class="course-access-item">
                                    <h4>${escapeHtml(course.title)}</h4>
                                    <div class="course-access-meta">
                                        <span class="course-access-status status-${course.status}">
                                            ${getStatusText(course.status)}
                                        </span>
                                        ${course.expires_formatted ? `| ${lilacCourseAccess.i18n.expires}: ${course.expires_formatted}` : ''}
                                    </div>
                                    <div class="course-access-actions">
                                        <button type="button" class="button button-small set-expiration-button"
                                                data-user-id="${user.ID}"
                                                data-course-id="${course.ID}"
                                                data-expires="${course.expires || ''}">
                                            ${lilacCourseAccess.i18n.set_expiration}
                                        </button>
                                    </div>
                                </div>
                            `).join('') 
                            : `<span class="no-courses">${lilacCourseAccess.i18n.no_courses}</span>`
                        }
                    </td>
                    <td>
                        <button type="button" class="button button-primary set-expiration-button"
                                data-user-id="${user.ID}">
                            ${lilacCourseAccess.i18n.manage_courses}
                        </button>
                    </td>
                </tr>
            `;
        });
        
        $userCoursesList.html(html);
    }
    
    function openExpirationDialog(userId, courseId, expires) {
        $expirationForm.trigger('reset');
        $customDateRow.hide();
        
        $('#expiration-user-id').val(userId);
        $('#expiration-course-id').val(courseId);
        
        if (expires === '0' || expires === 0) {
            $expirationType.val('permanent');
        } else if (expires) {
            const expiresDate = new Date(parseInt(expires) * 1000);
            $customDate.val(formatDateForInput(expiresDate));
            $expirationType.val('custom');
            $customDateRow.show();
        } else {
            $expirationType.val('1_month');
        }
        
        updateExpirationPreview();
        setExpirationDialog.dialog('open');
    }
    
    function saveExpiration() {
        const $submitButton = $expirationForm.find('button[type="submit"]');
        const $spinner = $expirationForm.find('.spinner');
        
        const userId = $('#expiration-user-id').val();
        const courseId = $('#expiration-course-id').val();
        const expirationType = $expirationType.val();
        const customDate = $customDate.val();
        
        let expires = 0;
        
        switch (expirationType) {
            case '1_week':
                expires = Math.floor(Date.now() / 1000) + (7 * 24 * 60 * 60);
                break;
            case '1_month':
                expires = Math.floor(Date.now() / 1000) + (30 * 24 * 60 * 60);
                break;
            case '3_months':
                expires = Math.floor(Date.now() / 1000) + (90 * 24 * 60 * 60);
                break;
            case '6_months':
                expires = Math.floor(Date.now() / 1000) + (180 * 24 * 60 * 60);
                break;
            case '1_year':
                expires = Math.floor(Date.now() / 1000) + (365 * 24 * 60 * 60);
                break;
            case 'custom':
                if (customDate) {
                    expires = Math.floor(new Date(customDate).getTime() / 1000);
                }
                break;
            case 'remove':
                expires = -1;
                break;
        }
        
        $submitButton.prop('disabled', true);
        $spinner.addClass('is-active');
        
        $.ajax({
            url: lilacCourseAccess.ajax_url,
            type: 'POST',
            data: {
                action: 'lilac_set_course_expiration',
                user_id: userId,
                course_id: courseId,
                expires: expires,
                nonce: lilacCourseAccess.nonce
            },
            success: function(response) {
                if (response.success) {
                    setExpirationDialog.dialog('close');
                    loadUserCourses();
                    showNotice('success', response.data.message);
                } else {
                    showError(response.data);
                }
            },
            error: function() {
                showError(lilacCourseAccess.i18n.error_saving);
            },
            complete: function() {
                $submitButton.prop('disabled', false);
                $spinner.removeClass('is-active');
            }
        });
    }
    
    function updateExpirationPreview() {
        const expirationType = $expirationType.val();
        let previewText = '';
        
        switch (expirationType) {
            case '1_week':
                previewText = formatRelativeDate(7);
                break;
            case '1_month':
                previewText = formatRelativeDate(30);
                break;
            case '3_months':
                previewText = formatRelativeDate(90);
                break;
            case '6_months':
                previewText = formatRelativeDate(180);
                break;
            case '1_year':
                previewText = formatRelativeDate(365);
                break;
            case 'custom':
                const customDate = $customDate.val();
                if (customDate) {
                    previewText = new Date(customDate).toLocaleDateString();
                } else {
                    previewText = 'Please select a date';
                }
                break;
            case 'remove':
                previewText = '<span style="color: #a00;">Access will be removed</span>';
                break;
            case 'permanent':
            default:
                previewText = '<span style="color: #00a32a;">Permanent access</span>';
                break;
        }
        
        $expirationPreview.html(previewText);
    }
    
    function resetFilters() {
        $userFilter.val('');
        $courseFilter.val('');
        loadUserCourses();
    }
    
    // Helper functions
    function formatRelativeDate(days) {
        const date = new Date();
        date.setDate(date.getDate() + days);
        return date.toLocaleDateString();
    }
    
    function formatDateForInput(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }
    
    function getStatusText(status) {
        const statusTexts = {
            'active': lilacCourseAccess.i18n.active,
            'expiring': lilacCourseAccess.i18n.expiring_soon,
            'expired': lilacCourseAccess.i18n.expired,
            'permanent': lilacCourseAccess.i18n.permanent
        };
        
        return statusTexts[status] || status;
    }
    
    function showNotice(type, message) {
        const noticeClass = type === 'error' ? 'notice-error' : 'notice-success';
        const notice = `
            <div class="notice ${noticeClass} is-dismissible">
                <p>${message}</p>
            </div>
        `;
        
        $('.notice').remove();
        $('.wrap h1').first().after(notice);
    }
    
    function showError(message) {
        showNotice('error', message);
    }
    
    function escapeHtml(unsafe) {
        if (typeof unsafe === 'undefined' || unsafe === null) {
            return '';
        }
        return String(unsafe)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
});
