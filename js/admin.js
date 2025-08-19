jQuery(document).ready(function($) {
    'use strict';

    // Cache DOM elements
    const $userFilter = $('#user-filter');
    const $courseFilter = $('#course-filter');
    const $filterButton = $('#filter-button');
    const $resetButton = $('#reset-filters');
    const $userCoursesList = $('#user-courses-list');
    const $setExpirationDialog = $('#set-expiration-dialog');
    const $viewCoursesDialog = $('#view-courses-dialog');
    const $expirationForm = $('#set-expiration-form');
    const $expirationType = $('#expiration-type');
    const $customDateRow = $('#custom-date-row');
    const $customDate = $('#custom-date');
    const $expirationPreview = $('#expiration-preview-text');
    
    // Initialize dialogs
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
    
    const viewCoursesDialog = $viewCoursesDialog.dialog({
        autoOpen: false,
        modal: true,
        width: 800,
        dialogClass: 'wp-dialog',
        closeOnEscape: true
    });
    
    // Load initial data
    loadUserCourses();
    
    // Event listeners
    $filterButton.on('click', loadUserCourses);
    $resetButton.on('click', resetFilters);
    
    $expirationType.on('change', function() {
        if ($(this).val() === 'custom') {
            $customDateRow.show();
            updateExpirationPreview();
        } else {
            $customDateRow.hide();
            updateExpirationPreview();
        }
    });
    
    $customDate.on('change', updateExpirationPreview);
    
    $expirationForm.on('submit', function(e) {
        e.preventDefault();
        saveExpiration();
    });
    
    $('.cancel-button').on('click', function() {
        setExpirationDialog.dialog('close');
    });
    
    // Handle click events on dynamically added elements
    $(document).on('click', '.set-expiration-button', function() {
        const userId = $(this).data('user-id');
        const courseId = $(this).data('course-id');
        const expires = $(this).data('expires');
        
        openExpirationDialog(userId, courseId, expires);
    });
    
    $(document).on('click', '.view-courses-button', function() {
        const userId = $(this).data('user-id');
        const userName = $(this).data('user-name');
        
        openViewCoursesDialog(userId, userName);
    });
    
    // Functions
    function loadUserCourses() {
        const userId = $userFilter.val() || '';
        const courseId = $courseFilter.val() || '';
        
        $userCoursesList.html(`
            <tr>
                <td colspan="4" class="loading">
                    <span class="spinner is-active"></span>
                    ${enhancedCourseAccess.i18n.loading}
                </td>
            </tr>
        `);
        
        $.ajax({
            url: enhancedCourseAccess.ajax_url,
            type: 'GET',
            data: {
                action: 'get_user_courses',
                user_id: userId,
                course_id: courseId,
                nonce: enhancedCourseAccess.nonce
            },
            success: function(response) {
                if (response.success) {
                    renderUserCourses(response.data);
                } else {
                    showError(response.data);
                }
            },
            error: function(xhr, status, error) {
                showError(enhancedCourseAccess.i18n.error_loading);
                console.error('Error loading user courses:', error);
            }
        });
    }
    
    function renderUserCourses(users) {
        if (!users || users.length === 0) {
            $userCoursesList.html(`
                <tr>
                    <td colspan="4" class="no-results">
                        ${enhancedCourseAccess.i18n.no_results}
                    </td>
                </tr>
            `);
            return;
        }
        
        let html = '';
        
        users.forEach(function(user) {
            html += `
                <tr>
                    <td>
                        <strong>${escapeHtml(user.display_name)}</strong>
                        <div class="row-actions">
                            <a href="#" class="view-courses-button" 
                               data-user-id="${user.ID}" 
                               data-user-name="${escapeHtml(user.display_name)}">
                                ${enhancedCourseAccess.i18n.view_courses}
                            </a>
                        </div>
                    </td>
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
                                        ${course.expires_formatted ? `| ${enhancedCourseAccess.i18n.expires}: ${course.expires_formatted}` : ''}
                                    </div>
                                    <div class="course-access-actions">
                                        <button type="button" class="button button-small set-expiration-button"
                                                data-user-id="${user.ID}"
                                                data-course-id="${course.ID}"
                                                data-expires="${course.expires || ''}">
                                            ${enhancedCourseAccess.i18n.set_expiration}
                                        </button>
                                    </div>
                                </div>
                            `).join('') 
                            : `<span class="no-courses">${enhancedCourseAccess.i18n.no_courses}</span>`
                        }
                    </td>
                    <td>
                        <button type="button" class="button button-primary view-courses-button"
                                data-user-id="${user.ID}"
                                data-user-name="${escapeHtml(user.display_name)}">
                            ${enhancedCourseAccess.i18n.manage_courses}
                        </button>
                    </td>
                </tr>
            `;
        });
        
        $userCoursesList.html(html);
    }
    
    function openExpirationDialog(userId, courseId, expires) {
        // Reset form
        $expirationForm.trigger('reset');
        $customDateRow.hide();
        
        // Set form values
        $('#expiration-user-id').val(userId);
        $('#expiration-course-id').val(courseId);
        
        // Set initial expiration type
        if (expires === '0' || expires === 0) {
            $expirationType.val('permanent');
            $expirationPreview.text(enhancedCourseAccess.i18n.never_expires);
        } else if (expires) {
            // Try to match the expiration with a preset
            const expiresDate = new Date(parseInt(expires) * 1000);
            $customDate.val(formatDateForInput(expiresDate));
            $expirationType.val('custom');
            $customDateRow.show();
            updateExpirationPreview();
        } else {
            // Default to 1 month
            $expirationType.val('1_month');
            updateExpirationPreview();
        }
        
        // Open dialog
        setExpirationDialog.dialog('option', 'title', enhancedCourseAccess.i18n.set_expiration);
        setExpirationDialog.dialog('open');
    }
    
    function openViewCoursesDialog(userId, userName) {
        const $content = $('#view-courses-content');
        const $title = $('#view-courses-title');
        
        $title.text(`${enhancedCourseAccess.i18n.courses_for} ${userName}`);
        $content.html(`
            <p class="loading">
                <span class="spinner is-active"></span>
                ${enhancedCourseAccess.i18n.loading}
            </p>
        `);
        
        // Load user's courses
        $.ajax({
            url: enhancedCourseAccess.ajax_url,
            type: 'GET',
            data: {
                action: 'get_user_courses',
                user_id: userId,
                nonce: enhancedCourseAccess.nonce
            },
            success: function(response) {
                if (response.success && response.data && response.data.length > 0) {
                    let html = '<table class="wp-list-table widefat fixed striped">';
                    html += `
                        <thead>
                            <tr>
                                <th>${enhancedCourseAccess.i18n.course}</th>
                                <th>${enhancedCourseAccess.i18n.status}</th>
                                <th>${enhancedCourseAccess.i18n.expires}</th>
                                <th>${enhancedCourseAccess.i18n.actions}</th>
                            </tr>
                        </thead>
                        <tbody>
                    `;
                    
                    response.data.forEach(function(course) {
                        html += `
                            <tr>
                                <td>
                                    <strong>${escapeHtml(course.title)}</strong><br>
                                    <small>ID: ${course.course_id}</small>
                                </td>
                                <td>
                                    <span class="course-access-status status-${course.status}">
                                        ${getStatusText(course.status)}
                                    </span>
                                </td>
                                <td>${course.expires_formatted || enhancedCourseAccess.i18n.never}</td>
                                <td>
                                    <button type="button" class="button button-small set-expiration-button"
                                            data-user-id="${userId}"
                                            data-course-id="${course.course_id}"
                                            data-expires="${course.expires || ''}">
                                        ${enhancedCourseAccess.i18n.set_expiration}
                                    </button>
                                </td>
                            </tr>
                        `;
                    });
                    
                    html += '</tbody></table>';
                    $content.html(html);
                } else {
                    $content.html(`<p>${enhancedCourseAccess.i18n.no_courses_found}</p>`);
                }
            },
            error: function(xhr, status, error) {
                $content.html(`<div class="error">${enhancedCourseAccess.i18n.error_loading}</div>`);
                console.error('Error loading user courses:', error);
            }
        });
        
        // Open dialog
        viewCoursesDialog.dialog('option', 'title', enhancedCourseAccess.i18n.manage_courses);
        viewCoursesDialog.dialog('open');
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
                expires = -1; // Special value to remove access
                break;
            // 'permanent' uses the default expires = 0
        }
        
        // Show loading state
        $submitButton.prop('disabled', true);
        $spinner.addClass('is-active');
        
        // Send request
        $.ajax({
            url: enhancedCourseAccess.ajax_url,
            type: 'POST',
            data: {
                action: 'set_course_expiration',
                user_id: userId,
                course_id: courseId,
                expires: expires,
                nonce: enhancedCourseAccess.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Close dialog and refresh data
                    setExpirationDialog.dialog('close');
                    loadUserCourses();
                    
                    // Show success message
                    const message = expires === -1 
                        ? enhancedCourseAccess.i18n.access_removed 
                        : enhancedCourseAccess.i18n.expiration_updated;
                    
                    showNotice('success', message);
                } else {
                    showError(response.data || enhancedCourseAccess.i18n.error_saving);
                }
            },
            error: function(xhr, status, error) {
                showError(enhancedCourseAccess.i18n.error_saving);
                console.error('Error saving expiration:', error);
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
                    const date = new Date(customDate);
                    previewText = date.toLocaleDateString();
                } else {
                    previewText = enhancedCourseAccess.i18n.select_date;
                }
                break;
            case 'remove':
                previewText = `<span style="color: #a00;">${enhancedCourseAccess.i18n.access_will_be_removed}</span>`;
                break;
            case 'permanent':
            default:
                previewText = `<span style="color: #00a32a;">${enhancedCourseAccess.i18n.permanent_access}</span>`;
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
            'active': enhancedCourseAccess.i18n.active,
            'expiring': enhancedCourseAccess.i18n.expiring_soon,
            'expired': enhancedCourseAccess.i18n.expired,
            'permanent': enhancedCourseAccess.i18n.permanent
        };
        
        return statusTexts[status] || status;
    }
    
    function showNotice(type, message) {
        const noticeClass = type === 'error' ? 'notice-error' : 'notice-success';
        const notice = `
            <div class="notice ${noticeClass} is-dismissible">
                <p>${message}</p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text">${enhancedCourseAccess.i18n.dismiss}</span>
                </button>
            </div>
        `;
        
        // Remove any existing notices
        $('.notice').remove();
        
        // Add new notice after the first h1
        $('.wrap h1').first().after(notice);
        
        // Make notice dismissible
        $(document).on('click', '.notice-dismiss', function() {
            $(this).closest('.notice').fadeOut(200, function() {
                $(this).remove();
            });
        });
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
