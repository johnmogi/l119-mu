<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="notice notice-info">
        <p><?php _e('Manage course access and expiration dates for users.', 'lilac-course-access'); ?></p>
    </div>
    
    <div class="tablenav top">
        <div class="alignleft actions">
            <select id="user-filter">
                <option value=""><?php _e('All Users', 'lilac-course-access'); ?></option>
                <?php foreach ($users as $user) : ?>
                    <option value="<?php echo esc_attr($user->ID); ?>">
                        <?php echo esc_html($user->display_name . ' (' . $user->user_email . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <select id="course-filter">
                <option value=""><?php _e('All Courses', 'lilac-course-access'); ?></option>
                <?php foreach ($courses as $course) : ?>
                    <option value="<?php echo esc_attr($course->ID); ?>">
                        <?php echo esc_html($course->post_title); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <button type="button" id="filter-button" class="button">
                <?php _e('Filter', 'lilac-course-access'); ?>
            </button>
            
            <button type="button" id="reset-filters" class="button">
                <?php _e('Reset', 'lilac-course-access'); ?>
            </button>
        </div>
    </div>
    
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e('User', 'lilac-course-access'); ?></th>
                <th><?php _e('Email', 'lilac-course-access'); ?></th>
                <th><?php _e('Course Access', 'lilac-course-access'); ?></th>
                <th><?php _e('Actions', 'lilac-course-access'); ?></th>
            </tr>
        </thead>
        <tbody id="user-courses-list">
            <tr>
                <td colspan="4" class="loading">
                    <span class="spinner is-active"></span>
                    <?php _e('Loading course access data...', 'lilac-course-access'); ?>
                </td>
            </tr>
        </tbody>
    </table>
    
    <!-- Set Expiration Dialog -->
    <div id="set-expiration-dialog" style="display: none;">
        <form id="set-expiration-form">
            <input type="hidden" name="user_id" id="expiration-user-id">
            <input type="hidden" name="course_id" id="expiration-course-id">
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="expiration-type"><?php _e('Expiration', 'lilac-course-access'); ?></label>
                    </th>
                    <td>
                        <select name="expiration_type" id="expiration-type" class="regular-text">
                            <option value="permanent"><?php _e('Permanent Access', 'lilac-course-access'); ?></option>
                            <option value="1_week"><?php _e('1 Week from now', 'lilac-course-access'); ?></option>
                            <option value="1_month" selected><?php _e('1 Month from now', 'lilac-course-access'); ?></option>
                            <option value="3_months"><?php _e('3 Months from now', 'lilac-course-access'); ?></option>
                            <option value="6_months"><?php _e('6 Months from now', 'lilac-course-access'); ?></option>
                            <option value="1_year"><?php _e('1 Year from now', 'lilac-course-access'); ?></option>
                            <option value="custom"><?php _e('Custom Date', 'lilac-course-access'); ?></option>
                            <option value="remove"><?php _e('Remove Access', 'lilac-course-access'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr id="custom-date-row" style="display: none;">
                    <th scope="row">
                        <label for="custom-date"><?php _e('Custom Date', 'lilac-course-access'); ?></label>
                    </th>
                    <td>
                        <input type="date" name="custom_date" id="custom-date" class="regular-text">
                    </td>
                </tr>
            </table>
            
            <div id="expiration-preview">
                <strong><?php _e('Expiration:', 'lilac-course-access'); ?></strong>
                <span id="expiration-preview-text"></span>
            </div>
            
            <div class="submit">
                <button type="submit" class="button button-primary">
                    <?php _e('Save Changes', 'lilac-course-access'); ?>
                </button>
                <button type="button" class="button cancel-button">
                    <?php _e('Cancel', 'lilac-course-access'); ?>
                </button>
                <span class="spinner"></span>
            </div>
        </form>
    </div>
</div>
