<?php
/**
 * Plugin Name: LearnDash Video Simple
 * Description: Simple video integration for LearnDash lessons
 * Version: 1.0.0
 * Author: Lilac Team
 */

/**
 * LearnDash Video Support Plugin
 * 
 * Minimal, non-destructive video support for LearnDash lessons
 * Preserves all existing design and functionality
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Test if plugin is loading and inject video script
add_action('wp_head', function() {
    echo "<!-- LearnDash Video Plugin: LOADED -->\n";
    echo "<!-- URGENT: If you see this comment, our plugin IS loading -->\n";
});

// Add a very obvious test on lesson pages
add_action('wp_footer', function() {
    if (is_singular('sfwd-lessons')) {
        echo "<!-- URGENT TEST: Our video plugin is definitely running on this lesson page -->\n";
    }
});

// Simple DOM injection approach - run in footer
add_action('wp_footer', function() {
    // Handle both lesson pages and course pages
    if (is_singular('sfwd-lessons')) {
        echo "<!-- This IS a lesson page! -->\n";
        inject_single_lesson_video();
    } elseif (is_singular('sfwd-courses')) {
        echo "<!-- This IS a course page! -->\n";
        inject_course_accordion_videos();
    } else {
        echo "<!-- Not a lesson or course page -->\n";
        return;
    }
});

// Enhanced mobile device detection
function is_mobile_device() {
    // First check WordPress built-in function
    if (function_exists('wp_is_mobile') && wp_is_mobile()) {
        return true;
    }
    
    // Enhanced user agent detection for mobile devices
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $mobile_patterns = [
        '/android/i',
        '/webos/i', 
        '/iphone/i',
        '/ipad/i',
        '/ipod/i',
        '/blackberry/i',
        '/windows phone/i',
        '/mobile/i',
        '/tablet/i'
    ];
    
    foreach ($mobile_patterns as $pattern) {
        if (preg_match($pattern, $user_agent)) {
            return true;
        }
    }
    
    return false;
}

// Helper: is URL pointing to local dev host
function ldv_is_local_url($url) {
    $host = parse_url($url, PHP_URL_HOST);
    if (!$host) return false;
    $host = strtolower($host);
    if ($host === 'localhost' || $host === '127.0.0.1') return true;
    // ends with .local
    return (substr($host, -6) === '.local');
}

// Validate that a remote URL exists (tolerant on local/HEAD-disabled servers)
function ldv_url_exists($url) {
    if (empty($url)) {
        return false;
    }
    if (!function_exists('wp_remote_head')) {
        return true; // Best effort if WP HTTP API not available
    }
    $is_local = ldv_is_local_url($url);
    $args = [ 'timeout' => 5, 'redirection' => 3, 'sslverify' => $is_local ? false : true ];
    $response = wp_remote_head($url, $args);
    if (is_wp_error($response)) {
        // Try a tiny GET range request as fallback (some servers block HEAD)
        $args['headers'] = [ 'Range' => 'bytes=0-0' ];
        $response = wp_remote_get($url, $args);
    }
    if (is_wp_error($response)) {
        error_log('LDV URL check failed (WP_Error) for ' . $url . ': ' . $response->get_error_message());
        return false;
    }
    $code = wp_remote_retrieve_response_code($response);
    // Accept common success/redirect codes; also accept 401/403/405 (HEAD not allowed)
    if (($code >= 200 && $code < 400) || in_array($code, [401, 403, 405, 206], true)) {
        return true;
    }
    error_log('LDV URL check HTTP code ' . $code . ' for ' . $url);
    return false;
}

// Function to inject video on single lesson page
function inject_single_lesson_video() {
    
    $lesson_id = get_the_ID();
    $lesson_settings = learndash_get_setting($lesson_id);
    $video_url = !empty($lesson_settings['lesson_video_url']) ? $lesson_settings['lesson_video_url'] : '';
    $video_enabled = !empty($lesson_settings['lesson_video_enabled']);
    
    echo "<!-- DEBUG: Desktop Video URL from LearnDash: {$video_url} -->\n";
    echo "<!-- DEBUG: Video enabled: " . ($video_enabled ? 'true' : 'false') . " -->\n";
    
    // Debug lesson materials - check all meta keys
    $all_meta = get_post_meta($lesson_id);
    echo "<!-- DEBUG: All lesson meta keys: " . print_r(array_keys($all_meta), true) . " -->\n";
    
    $lesson_materials = get_post_meta($lesson_id, '_learndash-lesson-display-content-settings', true);
    echo "<!-- DEBUG: Lesson materials data: " . print_r($lesson_materials, true) . " -->\n";
    
    // Try alternative meta keys
    $alt_materials = get_post_meta($lesson_id, 'lesson_materials', true);
    echo "<!-- DEBUG: Alt materials: " . print_r($alt_materials, true) . " -->\n";
    
    if (empty($video_url) || !$video_enabled) {
        echo "<!-- No video to inject -->\n";
        return;
    }
    
    // Generate video embed - support both YouTube and MP4
    $video_html = '';
    
    // Handle YouTube URLs
    if (strpos($video_url, 'youtu.be') !== false || strpos($video_url, 'youtube.com') !== false) {
        preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $video_url, $matches);
        if (!empty($matches[1])) {
            $video_id = $matches[1];
            $video_html = '<div class="ld-video"><iframe width="100%" height="400" src="https://www.youtube.com/embed/' . esc_attr($video_id) . '" frameborder="0" allowfullscreen></iframe></div>';
        }
    }
    // Handle MP4 and other video formats
    else {
        // Determine MIME type based on extension
        $extension = strtolower(pathinfo(parse_url($video_url, PHP_URL_PATH), PATHINFO_EXTENSION));
        $mime_types = [
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'ogg' => 'video/ogg',
            'mov' => 'video/quicktime',
            'avi' => 'video/x-msvideo'
        ];
        $mime_type = isset($mime_types[$extension]) ? $mime_types[$extension] : 'video/mp4';
        
        // Enhanced mobile video detection from multiple sources
        $mobile_video_url = get_mobile_video_url($lesson_id, $video_url, $mime_types);
        
        // Generate unique ID for this video
        $video_id = 'ld-video-' . md5($video_url);
        
        // Determine which video to use based on available sources
        $desktop_video = $video_url; // From LearnDash video URL field
        $mobile_video = !empty($mobile_video_url) ? $mobile_video_url : $video_url; // From lesson materials or fallback
        
        // Check if this is a direct video file (MP4, WebM, etc.)
        if (preg_match('/\.(' . implode('|', array_keys($mime_types)) . ')(?:\?.*)?$/i', $video_url)) {
            
            // Enhanced mobile device detection
            $is_mobile = is_mobile_device();
            
            echo "<!-- DEBUG: Is Mobile Device: " . ($is_mobile ? 'true' : 'false') . " -->\n";
            echo "<!-- DEBUG: Desktop Video: {$desktop_video} -->\n";
            echo "<!-- DEBUG: Mobile Video: {$mobile_video_url} -->\n";
            
            // Generate unique ID for this video
            $video_id = 'ld-video-' . md5($video_url);
            
            // Build comprehensive video HTML with multiple sources and mobile optimization
            $video_html = '<div class="ld-video-container ld-video-responsive" id="' . $video_id . '" style="width: 100%; max-width: 100%; height: auto; max-height: 75vh; margin: 0 auto 20px;">
                <video 
                    class="ld-video-player"
                    style="width: 100%; height: auto; max-height: 75vh; object-fit: contain; border-radius: 8px;"
                    controls 
                    preload="metadata"
                    playsinline
                    webkit-playsinline
                    x5-video-player-type="h5"
                    x5-video-orientation="landscape|portrait"
                    x5-video-player-fullscreen="true"
                    x5-video-ignore-metadata="true"
                    x5-video-play-inline="true">
                    <source src="' . esc_url($desktop_video) . '" type="' . $mime_type . '" media="(min-width: 768px)">' . 
                    (!empty($mobile_video_url) && $mobile_video_url !== $desktop_video ? '
                    <source src="' . esc_url($mobile_video_url) . '" type="' . $mime_type . '" media="(max-width: 767px)">' : '') . '
                    Your browser does not support the video tag.
                </video>
            </div>';
            
            // Add comprehensive CSS for mobile optimization
            $video_html .= '<style>
                #' . $video_id . ' {
                    position: relative;
                    width: 100%;
                    max-width: 100%;
                    margin: 15px 0;
                    background: #000;
                    border-radius: 8px;
                    overflow: hidden;
                }
                
                #' . $video_id . ' video {
                    width: 100%;
                    height: auto;
                    max-width: 100%;
                    display: block;
                    background: #000;
                    outline: none;
                    border: none;
                }
                
                /* Desktop styles */
                @media (min-width: 768px) {
                    #' . $video_id . ' {
                        padding-bottom: 56.25%; /* 16:9 aspect ratio */
                        height: 0;
                    }
                    
                    #' . $video_id . ' video {
                        position: absolute;
                        top: 0;
                        left: 0;
                        width: 100%;
                        height: 100%;
                        object-fit: contain;
                    }
                }
                
                /* Mobile styles - iPhone and Android optimization */
                @media (max-width: 767px) {
                    #' . $video_id . ' {
                        padding-bottom: 177.78%; /* 9:16 aspect ratio for vertical videos */
                        height: 0;
                    }
                    
                    #' . $video_id . ' video {
                        position: absolute;
                        top: 0;
                        left: 0;
                        width: 100%;
                        height: 100%;
                        object-fit: contain;
                    }
                }
                
                /* iPhone specific optimizations */
                @media (max-width: 767px) and (-webkit-min-device-pixel-ratio: 2) {
                    #' . $video_id . ' video {
                        -webkit-transform: translateZ(0);
                        transform: translateZ(0);
                        -webkit-backface-visibility: hidden;
                        backface-visibility: hidden;
                    }
                }
                
                /* Android specific optimizations */
                @media (max-width: 767px) {
                    #' . $video_id . ' video::-webkit-media-controls-fullscreen-button {
                        display: block;
                    }
                    
                    #' . $video_id . ' video::-webkit-media-controls {
                        overflow: visible !important;
                    }
                }
                
                /* Landscape mobile optimization */
                @media (max-width: 767px) and (orientation: landscape) {
                    #' . $video_id . ' {
                        padding-bottom: 56.25% !important; /* 16:9 for landscape */
                    }
                }
                
                /* Portrait mobile optimization */
                @media (max-width: 767px) and (orientation: portrait) {
                    #' . $video_id . ' {
                        padding-bottom: 177.78% !important; /* 9:16 for portrait */
                    }
                }
            </style>';
            
            // Add comprehensive JavaScript for mobile optimization
            $video_html .= '<script>
                document.addEventListener("DOMContentLoaded", function() {
                    const video = document.querySelector("#' . $video_id . ' video");
                    if (!video) return;
                    // Diagnostics and gentle load nudge for local/preview environments
                    try { video.load(); } catch (e) { console.warn("video.load() threw", e); }
                    console.log("Initial states:", { readyState: video.readyState, networkState: video.networkState });
                    setTimeout(function() {
                        console.log("Post-load states:", { readyState: video.readyState, networkState: video.networkState });
                        if (video.readyState < 2) {
                            console.warn("Video not ready (readyState=" + video.readyState + "). If using IDE preview, open in a normal browser and trust the certificate.");
                            // If browser didn\'t pick a source, force-assign the desktop MP4 and load
                            if (!video.currentSrc) {
                                try {
                                    video.src = "' . esc_url($desktop_video) . '";
                                    console.log("Assigned desktop src explicitly and calling load()");
                                    video.load();
                                } catch (e) {
                                    console.warn("Setting src/load failed", e);
                                }
                            }
                            // Add a temporary Test Play button for diagnostics
                            try {
                                var container = document.getElementById("' . $video_id . '");
                                if (container && !container.querySelector(".ldv-debug-play")) {
                                    var btn = document.createElement("button");
                                    btn.textContent = "Test Play";
                                    btn.className = "ldv-debug-play";
                                    btn.style.cssText = "position:absolute;z-index:20;bottom:10px;right:10px;padding:6px 10px;font-size:12px;opacity:0.85;";
                                    btn.addEventListener("click", function() {
                                        var p = video.play();
                                        if (p && typeof p.then === "function") {
                                            p.then(function(){ console.log("Manual play started"); }).catch(function(err){ console.warn("Manual play failed", err); });
                                        }
                                    });
                                    container.appendChild(btn);
                                }
                            } catch (e) { console.warn("Could not add Test Play button", e); }
                        }
                    }, 600);
                    const isMobile = /iPhone|iPad|iPod|Android|BlackBerry|Opera Mini|IEMobile/i.test(navigator.userAgent);
                    const isIOS = /iPhone|iPad|iPod/i.test(navigator.userAgent);
                    const isAndroid = /Android/i.test(navigator.userAgent);
                    console.log("Device detection - Mobile:", isMobile, "iOS:", isIOS, "Android:", isAndroid);
                    if (isMobile) {
                        // Essential mobile attributes
                        video.setAttribute("playsinline", "true");
                        video.setAttribute("webkit-playsinline", "true");
                        video.setAttribute("x-webkit-airplay", "allow");
                        // iOS specific optimizations
                        if (isIOS) {
                            video.style.webkitTransform = "translateZ(0)";
                            video.style.webkitBackfaceVisibility = "hidden";
                            video.addEventListener("webkitbeginfullscreen", function() { console.log("iOS entering fullscreen"); });
                            video.addEventListener("webkitendfullscreen", function() { console.log("iOS exiting fullscreen"); });
                        }
                        // Android specific optimizations
                        if (isAndroid) {
                            video.setAttribute("preload", "metadata");
                            video.addEventListener("loadedmetadata", function() { console.log("Android video metadata loaded"); });
                        }
                        // Handle orientation changes for all mobile devices
                        let orientationTimeout;
                        window.addEventListener("orientationchange", function() {
                            clearTimeout(orientationTimeout);
                            orientationTimeout = setTimeout(function() {
                                console.log("Orientation changed - refreshing video");
                                if (!video.paused) {
                                    const currentTime = video.currentTime;
                                    video.pause();
                                    setTimeout(function() {
                                        video.currentTime = currentTime;
                                        video.play().catch(function(e) { console.log("Video play failed after orientation change:", e); });
                                    }, 100);
                                }
                            }, 500);
                        });
                        // Handle mobile network changes
                        if ("connection" in navigator) {
                            navigator.connection.addEventListener("change", function() {
                                console.log("Network connection changed:", navigator.connection.effectiveType);
                                if (navigator.connection.effectiveType === "slow-2g" || navigator.connection.effectiveType === "2g") {
                                    video.setAttribute("preload", "none");
                                    console.log("Slow connection detected - disabled preload");
                                }
                            });
                        }
                    }
                    // Error handling for all devices
                    video.addEventListener("error", function(e) {
                        console.error("Video error:", e);
                        const error = video.error;
                        if (error) {
                            console.error("Video error code:", error.code, "Message:", error.message);
                        }
                    });
                    // Load events
                    video.addEventListener("loadstart", function() { console.log("Video loading started"); });
                    video.addEventListener("canplay", function() { console.log("Video can start playing"); });
                    console.log("Video initialized with sources:", Array.from(video.querySelectorAll("source")).map(function(s){ return s.src; }));
                });
            </script>';
        }
        // Handle other video URLs (Vimeo, etc.) - fallback to iframe
        else if (filter_var($video_url, FILTER_VALIDATE_URL)) {
            $video_html = '<div class="ld-video"><iframe width="100%" height="400" src="' . esc_url($video_url) . '" frameborder="0" allowfullscreen></iframe></div>';
        }
    }
    
    if (empty($video_html)) {
        echo "<!-- Could not generate video HTML -->\n";
        return;
    }
    
    echo "<!-- Generated video HTML -->\n";
    
    // JavaScript DOM injection
    ?>
    <script>
    console.log('🚀 LearnDash Video Plugin: Starting injection...');
    console.log('📹 Video URL:', <?php echo json_encode($video_url); ?>);
    
    document.addEventListener('DOMContentLoaded', function() {
        console.log('📄 DOM loaded, injecting video...');
        
        // Try multiple injection points - prioritize higher positions
        const selectors = [
            '.ld-lesson-content-wrapper',
            '.ld-lesson-content',
            '.learndash-wrapper .ld-item-wrap',
            '.elementor-widget-theme-post-content .elementor-widget-container',
            '.entry-content',
            '.learndash-wrapper',
            'main .learndash',
            'main',
            'article',
            'body'
        ];
        
        let injected = false;
        
        for (let selector of selectors) {
            const target = document.querySelector(selector);
            if (target && !injected) {
                console.log('✅ Found target:', selector);
                
                const videoDiv = document.createElement('div');
                videoDiv.innerHTML = <?php echo json_encode($video_html); ?>;
                videoDiv.style.margin = '0'; // Remove all margins including bottom
                videoDiv.style.padding = '0';
                
                // Make iframe responsive with larger size for desktop
                const iframe = videoDiv.querySelector('iframe');
                if (iframe) {
                    iframe.style.width = '100%';
                    iframe.style.height = '600px'; // Increased height for better desktop viewing
                    iframe.style.maxWidth = '100%';
                    iframe.style.display = 'block';
                    iframe.style.border = 'none';
                    iframe.style.borderRadius = '8px';
                    iframe.style.boxShadow = '0 4px 12px rgba(0,0,0,0.1)';
                }
                
                target.insertBefore(videoDiv, target.firstChild);
                console.log('📺 Video injected successfully!');

                // Diagnostics that DO execute (outer script runs)
                try {
                    const container = videoDiv.querySelector('#' + <?php echo json_encode($video_id); ?>);
                    const video = container ? container.querySelector('video') : null;
                    if (video) {
                        try { video.load(); } catch (e) { console.warn('video.load() threw', e); }
                        // Log available sources and selection
                        try {
                            console.log('Sources:', Array.from(video.querySelectorAll('source')).map(function(s){ return s.src; }));
                            console.log('currentSrc:', video.currentSrc);
                            console.log('video.src:', video.src);
                            console.log('canPlayType mp4:', video.canPlayType('video/mp4'));
                            console.log('canPlayType h264:', video.canPlayType('video/mp4; codecs="avc1.42E01E, mp4a.40.2"'));
                        } catch (e) { console.warn('Source logging failed', e); }
                        console.log('Initial states:', { readyState: video.readyState, networkState: video.networkState });
                        
                        // Add error event listener immediately
                        video.addEventListener('error', function(e) {
                            console.error('Video error event:', e);
                            const error = video.error;
                            if (error) {
                                console.error('Video error details:', {
                                    code: error.code,
                                    message: error.message,
                                    MEDIA_ERR_ABORTED: error.code === 1,
                                    MEDIA_ERR_NETWORK: error.code === 2, 
                                    MEDIA_ERR_DECODE: error.code === 3,
                                    MEDIA_ERR_SRC_NOT_SUPPORTED: error.code === 4
                                });
                            }
                        });
                        setTimeout(function() {
                            console.log('Post-load states:', { readyState: video.readyState, networkState: video.networkState });
                            if (video.readyState < 2) {
                                try {
                                    // Relax constraints and force source selection
                                    video.removeAttribute('crossorigin');
                                    try { video.preload = 'auto'; } catch (e) {}
                                    video.src = <?php echo json_encode($desktop_video); ?>;
                                    console.log('Forced src assignment to desktop MP4 and calling load()');
                                    video.load();
                                } catch (e) {
                                    console.warn('Forced assignment failed', e);
                                }
                                if (!container.querySelector('.ldv-debug-play')) {
                                    const btn = document.createElement('button');
                                    btn.textContent = 'Test Play';
                                    btn.className = 'ldv-debug-play';
                                    btn.style.cssText = 'position:absolute;z-index:20;bottom:10px;right:10px;padding:6px 10px;font-size:12px;opacity:0.85;';
                                    btn.addEventListener('click', function() {
                                        const p = video.play();
                                        if (p && typeof p.then === 'function') {
                                            p.then(function(){ console.log('Manual play started'); }).catch(function(err){ console.warn('Manual play failed', err); });
                                        }
                                    });
                                    container.appendChild(btn);
                                }
                            }
                        }, 600);
                    }
                } catch (e) { console.warn('Diagnostics block failed', e); }
                injected = true;
                break;
            }
        }
        
        if (!injected) {
            console.warn('❌ Could not find injection point');
        }
    });
    </script>
    <?php
}

// Function to inject videos in course page accordion
function inject_course_accordion_videos() {
    $course_id = get_the_ID();
    
    // Get all lessons in this course
    $lessons = learndash_get_course_lessons_list($course_id);
    
    if (empty($lessons)) {
        echo "<!-- No lessons found in course -->\n";
        return;
    }
    
    echo "<!-- Found " . count($lessons) . " lessons in course -->\n";
    
    // Prepare lesson video data for JavaScript
    $lesson_videos = array();
    
    foreach ($lessons as $lesson) {
        $lesson_id = $lesson['post']->ID;
        $lesson_settings = learndash_get_setting($lesson_id);
        $video_url = !empty($lesson_settings['lesson_video_url']) ? $lesson_settings['lesson_video_url'] : '';
        $video_enabled = !empty($lesson_settings['lesson_video_enabled']);
        
        if (!empty($video_url) && $video_enabled) {
            $video_html = '';
            
            // Handle YouTube URLs
            if (strpos($video_url, 'youtu.be') !== false || strpos($video_url, 'youtube.com') !== false) {
                preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $video_url, $matches);
                if (!empty($matches[1])) {
                    $video_id = $matches[1];
                    $video_html = '<div class="ld-video" style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; max-width: 100%;">' .
                        '<iframe style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 0;" ' .
                        'src="https://www.youtube.com/embed/' . esc_attr($video_id) . '?rel=0" ' .
                        'allowfullscreen></iframe></div>';
                }
            }
            // Handle MP4 and other video formats
            elseif (preg_match('/\.(mp4|webm|ogg|mov|avi)(\?.*)?$/i', $video_url)) {
                // Determine MIME type based on extension
                $extension = strtolower(pathinfo(parse_url($video_url, PHP_URL_PATH), PATHINFO_EXTENSION));
                $mime_types = [
                    'mp4' => 'video/mp4',
                    'webm' => 'video/webm',
                    'ogg' => 'video/ogg',
                    'mov' => 'video/quicktime',
                    'avi' => 'video/x-msvideo'
                ];
                $mime_type = isset($mime_types[$extension]) ? $mime_types[$extension] : 'video/mp4';
                
                // Check if there's a mobile version of the video
                $mobile_video_url = '';
                
                // Try different mobile video naming conventions
                $mobile_patterns = [
                    // Replace .mp4 with -mobile.mp4
                    '/\.(' . implode('|', array_keys($mime_types)) . ')(?:\?.*)?$/i' => '-mobile.$1',
                    // Add /mobile/ before filename
                    '/\/([^\/]+)\.(' . implode('|', array_keys($mime_types)) . ')(?:\?.*)?$/i' => '/mobile/$1.$2'
                ];
                
                foreach ($mobile_patterns as $pattern => $replacement) {
                    $potential_mobile_url = preg_replace($pattern, $replacement, $video_url);
                    if ($potential_mobile_url !== $video_url) {
                        $mobile_video_url = $potential_mobile_url;
                        break;
                    }
                }
                
                // Generate unique ID for this video
                $video_id = 'ld-video-accordion-' . md5($video_url);
                
                $video_html = '<div class="ld-video ld-video-responsive" id="' . $video_id . '" style="position: relative; width: 100%; height: auto; background-color: #000; border-radius: 8px; overflow: hidden; aspect-ratio: 16/9;">
                    <video 
                        class="ld-video-player"
                        style="width: 100%; height: 100%; object-fit: contain; border-radius: 8px;"
                        controls 
                        preload="auto"
                        crossorigin="anonymous"
                        playsinline
                        webkit-playsinline
                        x5-video-player-type="h5"
                        x5-video-orientation="portrait"
                        x5-video-player-fullscreen="true"
                        x5-video-ignore-metadata="true"
                        x5-video-play-inline="true">
                        <source src="' . esc_url($video_url) . '" type="' . $mime_type . '" media="(min-width: 768px)">' . 
                        (!empty($mobile_video_url) ? '
                        <source src="' . esc_url($mobile_video_url) . '" type="' . $mime_type . '" media="(max-width: 767px)">' : '') . '
                        <source src="' . esc_url($video_url) . '" type="' . $mime_type . '">
                        Your browser does not support the video tag.
                    </video>
                    <style>
                        /* Mobile styles */
                        @media (max-width: 767px) {
                            #' . $video_id . ' {
                                aspect-ratio: unset;
                                max-height: 100vh !important;
                            }
                            #' . $video_id . ' .ld-video-player checkme {
                                position: absolute;
                                top: 50%;
                                left: 50%;
                                transform: translate(-50%, -50%);
                                width: 100% !important;
                                height: 100% !important;
                                min-height: 65vh;
                                object-fit: contain;
                            }
                        }
                        /* Desktop styles */
                        @media (min-width: 768px) {
                            #' . $video_id . ' {
                                max-width: 900px;
                                margin: 0 auto 20px;
                            }
                        }
                    </style>
                </div>';
            }
            // Handle other video URLs (Vimeo, etc.) - fallback to iframe
            elseif (filter_var($video_url, FILTER_VALIDATE_URL)) {
                $video_html = '<div class="ld-video" style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; max-width: 100%;">' .
                    '<iframe style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 0;" ' .
                    'src="' . esc_url($video_url) . '" allowfullscreen></iframe></div>';
            }
            
            if (!empty($video_html)) {
                $lesson_videos[$lesson_id] = array(
                    'url' => $video_url,
                    'html' => $video_html,
                    'slug' => $lesson['post']->post_name
                );
            }
        }
    }
    
    if (empty($lesson_videos)) {
        echo "<!-- No videos to inject in course -->\n";
        return;
    }
    
    echo "<!-- Found " . count($lesson_videos) . " videos to inject -->\n";
    
    // JavaScript to inject videos into accordion
    ?>
    <script>
    console.log('🎥 Course Page: Injecting " . count($lesson_videos) . " videos into accordion...');
    
    const lessonVideos = <?php echo json_encode($lesson_videos); ?>;
    
    document.addEventListener('DOMContentLoaded', function() {
        console.log('📄 Course DOM loaded, searching for lesson accordion items...');
        
        // Wait a bit for Elementor/LearnDash to fully render
        setTimeout(function() {
            Object.keys(lessonVideos).forEach(function(lessonId) {
                const videoData = lessonVideos[lessonId];
                
                // Try to find the topic accordion for this lesson
                const topicListSelectors = [
                    `.ld-topic-list-${lessonId}`,
                    `.ld-table-list-items.ld-topic-list-${lessonId}`,
                    `#ld-topic-list-${lessonId}`
                ];
                
                let topicList = null;
                for (let selector of topicListSelectors) {
                    topicList = document.querySelector(selector);
                    if (topicList) {
                        console.log('✅ Found topic accordion for lesson ' + lessonId + ':', selector);
                        break;
                    }
                }
                
                if (topicList && !topicList.querySelector('.ld-lesson-video-injection')) {
                    // Create video wrapper that looks like a topic item
                    const videoWrapper = document.createElement('div');
                    videoWrapper.className = 'ld-table-list-item ld-lesson-video-injection';
                    videoWrapper.style.marginBottom = '10px';
                    videoWrapper.style.backgroundColor = '#f9f9f9';
                    videoWrapper.style.padding = '12px';
                    videoWrapper.style.border = '1px solid #ddd';
                    videoWrapper.style.borderRadius = '4px';
                    
                    // Create video container
                    const videoDiv = document.createElement('div');
                    videoDiv.innerHTML = videoData.html;
                    
                    // Fix styling for both iframe and video elements
                    const iframe = videoDiv.querySelector('iframe');
                    const video = videoDiv.querySelector('video');
                    
                    if (iframe) {
                        iframe.style.width = '100%';
                        iframe.style.height = '450px'; // Increased height for better visibility
                        iframe.style.maxWidth = '100%';
                        iframe.style.display = 'block';
                        iframe.removeAttribute('height'); // Remove conflicting height attribute
                    }
                    
                    if (video) {
                        video.style.width = '100%';
                        video.style.height = 'auto';
                        video.style.maxWidth = '100%';
                        video.style.display = 'block';
                        video.style.borderRadius = '8px';
                        video.style.boxShadow = '0 2px 8px rgba(0,0,0,0.1)';
                    }
                    
                    // Ensure the video container has proper styling
                    const videoContainer = videoDiv.querySelector('.ld-video');
                    if (videoContainer) {
                        videoContainer.style.position = 'relative';
                        videoContainer.style.maxWidth = '100%';
                        videoContainer.style.margin = '15px 0';
                    }
                    
                    videoWrapper.appendChild(videoDiv);
                    
                    // Insert as the first element in the topic list
                    topicList.insertBefore(videoWrapper, topicList.firstChild);
                    console.log('📺 Video injected as first element in topic accordion for lesson ' + lessonId);
                    
                    // Add enhanced diagnostics for course page videos
                    if (video) {
                        try { video.load(); } catch (e) { console.warn('video.load() threw', e); }
                        // Log available sources and selection
                        try {
                            console.log('Course Video Sources:', Array.from(video.querySelectorAll('source')).map(function(s){ return s.src; }));
                            console.log('Course Video currentSrc:', video.currentSrc);
                            console.log('Course Video canPlayType mp4:', video.canPlayType('video/mp4'));
                            console.log('Course Video canPlayType mp4+codecs:', video.canPlayType('video/mp4; codecs="avc1.42E01E, mp4a.40.2"'));
                        } catch (e) { console.warn('Course video source logging failed', e); }
                        console.log('Course Video Initial states:', { readyState: video.readyState, networkState: video.networkState });
                        
                        // Add error event listener with codec fallback
                        video.addEventListener('error', function(e) {
                            console.error('Course Video error:', e);
                            const error = video.error;
                            if (error) {
                                console.error('Course Video error code:', error.code, 'Message:', error.message);
                                
                                // If codec error (code 4), try fallback approaches
                                if (error.code === 4) {
                                    console.warn('Codec not supported - trying fallback solutions');
                                    
                                    // Replace video with download link and iframe attempt
                                    const container = video.closest('.ld-video');
                                    if (container) {
                                        container.innerHTML = `
                                            <div style="background:#f0f0f0;padding:20px;text-align:center;border-radius:8px;">
                                                <p style="margin:0 0 15px 0;color:#666;">
                                                    <strong>Video format not supported by browser</strong><br>
                                                    The video uses codecs not supported by Chrome/Edge.
                                                </p>
                                                <div style="margin-bottom:15px;">
                                                    <a href="${videoData.url}" target="_blank" 
                                                       style="display:inline-block;background:#0073aa;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;margin:5px;">
                                                        📥 Download Video
                                                    </a>
                                                    <button onclick="this.nextElementSibling.style.display='block';this.style.display='none';" 
                                                            style="background:#28a745;color:white;padding:10px 20px;border:none;border-radius:5px;margin:5px;cursor:pointer;">
                                                        🎬 Try VLC Web Player
                                                    </button>
                                                </div>
                                                <iframe src="${videoData.url}" width="100%" height="400" 
                                                        style="display:none;border:1px solid #ddd;border-radius:5px;" 
                                                        allow="autoplay">
                                                </iframe>
                                                <p style="margin:15px 0 0 0;font-size:12px;color:#999;">
                                                    💡 Tip: Use Firefox or Safari for better codec support, or download and play in VLC
                                                </p>
                                            </div>
                                        `;
                                    }
                                }
                            }
                        });
                        
                        setTimeout(function() {
                            console.log('Course Video Post-load states:', { readyState: video.readyState, networkState: video.networkState });
                            if (video.readyState < 2) {
                                try {
                                    // Relax constraints and force source selection
                                    video.removeAttribute('crossorigin');
                                    try { video.preload = 'auto'; } catch (e) {}
                                    video.playsInline = true;
                                    video.muted = true;
                                    
                                    // Rebuild sources cleanly
                                    const sources = video.querySelectorAll('source');
                                    sources.forEach(function(s) { s.remove(); });
                                    
                                    // Try direct src assignment first (bypasses source selection)
                                    video.src = videoData.url;
                                    console.log('Course Video: Set direct src to', videoData.url);
                                    video.load();
                                    
                                    // Also add a fallback source with detailed codec info
                                    const newSource = document.createElement('source');
                                    newSource.src = videoData.url;
                                    newSource.type = 'video/mp4; codecs="avc1.42E01E, mp4a.40.2"';
                                    video.appendChild(newSource);
                                    
                                    console.log('Course Video: Added fallback source with codecs');
                                    video.load();
                                } catch (e) {
                                    console.warn('Course Video forced assignment failed', e);
                                }
                                
                                // Add Test Play button with enhanced debugging
                                const container = video.closest('.ld-video');
                                if (container && !container.querySelector('.ldv-debug-play')) {
                                    const btn = document.createElement('button');
                                    btn.textContent = 'Test Play';
                                    btn.className = 'ldv-debug-play';
                                    btn.style.cssText = 'position:absolute;z-index:20;bottom:10px;right:10px;padding:6px 10px;font-size:12px;opacity:0.85;background:rgba(0,0,0,0.7);color:white;border:none;border-radius:3px;cursor:pointer;';
                                    btn.addEventListener('click', function(e) {
                                        e.preventDefault();
                                        e.stopPropagation();
                                        console.log('Test Play clicked! Video state:', {
                                            readyState: video.readyState,
                                            networkState: video.networkState,
                                            currentSrc: video.currentSrc,
                                            src: video.src,
                                            paused: video.paused
                                        });
                                        
                                        // Force reload if no source
                                        if (!video.currentSrc && !video.src) {
                                            video.src = videoData.url;
                                            video.load();
                                            console.log('Forced reload with direct src');
                                        }
                                        
                                        const p = video.play();
                                        if (p && typeof p.then === 'function') {
                                            p.then(function(){ 
                                                console.log('Course Video manual play started successfully'); 
                                                btn.textContent = 'Playing!';
                                                btn.style.background = 'rgba(0,128,0,0.7)';
                                            }).catch(function(err){ 
                                                console.warn('Course Video manual play failed', err);
                                                btn.textContent = 'Play Failed';
                                                btn.style.background = 'rgba(128,0,0,0.7)';
                                            });
                                        } else {
                                            console.log('video.play() did not return a promise');
                                        }
                                    });
                                    container.appendChild(btn);
                                }
                            }
                        }, 600);
                    }
                    
                    injected = true;
                } else if (!topicList) {
                    console.warn('❌ Could not find topic accordion for lesson ' + lessonId);
                } else {
                    console.log('ℹ️ Video already exists for lesson ' + lessonId);
                }
                
                if (!injected) {
                    console.warn('❌ Could not find injection point for lesson ' + lessonId);
                }
            });
        }, 1000); // Wait 1 second for accordion to render
    });
    </script>
    <?php
}

// Enhanced function to detect mobile video URLs from various sources
function get_mobile_video_url($lesson_id, $desktop_video_url, $mime_types) {
    $mobile_video_url = '';
    
    // Method 1: Check _sfwd-lessons meta (lesson materials)
    $sfwd_lessons = get_post_meta($lesson_id, '_sfwd-lessons', true);
    echo "<!-- DEBUG: _sfwd-lessons meta: " . print_r($sfwd_lessons, true) . " -->\n";
    
    if (!empty($sfwd_lessons['sfwd-lessons_lesson_materials_enabled']) && 
        $sfwd_lessons['sfwd-lessons_lesson_materials_enabled'] === 'on' &&
        !empty($sfwd_lessons['sfwd-lessons_lesson_materials'])) {
        
        $materials_content = trim($sfwd_lessons['sfwd-lessons_lesson_materials']);
        
        // Check if it's a direct video URL
        if (preg_match('/https?:\/\/[^\s<>"\']+(\.' . implode('|', array_keys($mime_types)) . ')(?:\?[^\s<>"\']*)?/i', $materials_content, $matches)) {
            $mobile_video_url = $matches[0];
            echo "<!-- DEBUG: Found mobile video URL in _sfwd-lessons: {$mobile_video_url} -->\n";
        }
        // Check if it contains HTML with video URLs
        elseif (preg_match('/<source[^>]+src=["\'"]([^"\'">]+)["\'"][^>]*>/i', $materials_content, $matches)) {
            $mobile_video_url = $matches[1];
            echo "<!-- DEBUG: Found mobile video URL in HTML source tag: {$mobile_video_url} -->\n";
        }
        // Check for any URL in the content
        elseif (preg_match('/https?:\/\/[^\s<>"\'">]+/i', $materials_content, $matches)) {
            $potential_url = $matches[0];
            // Verify it's a video file
            if (preg_match('/\.' . implode('|', array_keys($mime_types)) . '(?:\?.*)?$/i', $potential_url)) {
                $mobile_video_url = $potential_url;
                echo "<!-- DEBUG: Found potential mobile video URL: {$mobile_video_url} -->\n";
            }
        }
    }
    
    // Method 2: Check post content for embedded videos
    if (empty($mobile_video_url)) {
        $post_content = get_post_field('post_content', $lesson_id);
        if (!empty($post_content)) {
            // Look for video tags with mobile sources
            if (preg_match('/<source[^>]+media=["\'"][^"\'">]*mobile[^"\'">]*["\'"][^>]+src=["\'"]([^"\'">]+)["\'"][^>]*>/i', $post_content, $matches)) {
                $mobile_video_url = $matches[1];
                echo "<!-- DEBUG: Found mobile video in post content (media query): {$mobile_video_url} -->\n";
            }
            // Look for any video URL in content
            elseif (preg_match('/https?:\/\/[^\s<>"\']+(\.' . implode('|', array_keys($mime_types)) . ')(?:\?[^\s<>"\']*)?/i', $post_content, $matches)) {
                $mobile_video_url = $matches[0];
                echo "<!-- DEBUG: Found video URL in post content: {$mobile_video_url} -->\n";
            }
        }
    }
    
    // Method 3: Check alternative meta fields
    if (empty($mobile_video_url)) {
        $meta_keys_to_check = [
            '_learndash-lesson-display-content-settings',
            'lesson_materials',
            'mobile_video_url',
            'lesson_mobile_video',
            '_lesson_mobile_video'
        ];
        
        foreach ($meta_keys_to_check as $meta_key) {
            $meta_value = get_post_meta($lesson_id, $meta_key, true);
            if (!empty($meta_value)) {
                if (is_array($meta_value) && isset($meta_value['lesson_materials'])) {
                    $content = $meta_value['lesson_materials'];
                } elseif (is_string($meta_value)) {
                    $content = $meta_value;
                } else {
                    continue;
                }
                
                if (preg_match('/https?:\/\/[^\s<>"\']+(\.' . implode('|', array_keys($mime_types)) . ')(?:\?[^\s<>"\']*)?/i', $content, $matches)) {
                    $mobile_video_url = $matches[0];
                    echo "<!-- DEBUG: Found mobile video URL in meta {$meta_key}: {$mobile_video_url} -->\n";
                    break;
                }
            }
        }
    }
    
    // Method 4: Generate mobile video URL from desktop URL patterns
    if (empty($mobile_video_url) && !empty($desktop_video_url)) {
        $mobile_patterns = [
            // Replace .mp4 with -mobile.mp4
            '/\.(' . implode('|', array_keys($mime_types)) . ')(?:\?.*)?$/i' => '-mobile.$1',
            // Replace /videos/ with /videos/mobile/
            '/\/videos\//i' => '/videos/mobile/',
            // Add /mobile/ before filename
            '/\/([^\/]+\.' . implode('|', array_keys($mime_types)) . ')(?:\?.*)?$/i' => '/mobile/$1'
        ];
        
        foreach ($mobile_patterns as $pattern => $replacement) {
            $potential_mobile_url = preg_replace($pattern, $replacement, $desktop_video_url);
            if ($potential_mobile_url !== $desktop_video_url) {
                echo "<!-- DEBUG: Generated potential mobile URL: {$potential_mobile_url} -->\n";
                // Note: We don't verify if this URL exists, but we provide it as an option
                $mobile_video_url = $potential_mobile_url;
                break;
            }
        }
    }
    
    echo "<!-- DEBUG: Final mobile video URL: {$mobile_video_url} -->\n";
    return $mobile_video_url;
}

// Plugin is active and working

/**
 * Simple LearnDash Video Support Class
 * 
 * This class provides minimal video support without affecting design
 */
if (!class_exists('Lilac_LearnDash_Video_Simple')) {
class Lilac_LearnDash_Video_Simple {
    
    public function __construct() {
        // Only initialize if LearnDash is active
        if (!class_exists('SFWD_LMS')) {
            return;
        }
        
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks - DOM injection approach for Elementor compatibility
     */
    private function init_hooks() {
        // Enable LearnDash video processing (required constant)
        add_action('init', array($this, 'enable_video_constant'), 1);
        
        // Test if plugin is loading at all
        add_action('wp_head', array($this, 'test_plugin_loading'));
        
        // Use DOM injection instead of content filter (Elementor bypasses the_content)
        add_action('wp_footer', array($this, 'inject_video_script'));
    }
    
    /**
     * Test if plugin is loading at all
     */
    public function test_plugin_loading() {
        echo "<!-- 🚀 LearnDash Video Plugin: LOADED AND RUNNING! -->\n";
        if (is_singular('sfwd-lessons')) {
            echo "<!-- 🎯 This is a lesson page! -->\n";
        } else {
            echo "<!-- 📄 This is NOT a lesson page -->\n";
        }
    }
    
    /**
     * Enable the required LearnDash video constant
     */
    public function enable_video_constant() {
        if (!defined('LEARNDASH_LESSON_VIDEO')) {
            define('LEARNDASH_LESSON_VIDEO', true);
        }
    }
    
    /**
     * Inject video script into footer - DOM injection approach for Elementor compatibility
     */
    public function inject_video_script() {
        // Debug: Always output this comment to verify plugin is running
        echo "<!-- 🔥 LearnDash Video Plugin: inject_video_script called -->\n";
        
        // Only run on single lesson pages
        if (!is_singular('sfwd-lessons')) {
            echo "<!-- ❌ Not a single lesson page -->\n";
            return;
        }
        
        echo "<!-- ✅ This IS a single lesson page -->\n";
        
        $lesson_id = get_the_ID();
        $video_url = $this->get_lesson_video_url($lesson_id);
        
        if (empty($video_url)) {
            echo "<!-- LearnDash Video Plugin: No video URL found for lesson {$lesson_id} -->\n";
            return;
        }
        
        $lesson_settings = learndash_get_setting($lesson_id);
        $video_enabled = !empty($lesson_settings['lesson_video_enabled']);
        $video_position = !empty($lesson_settings['lesson_video_shown']) ? $lesson_settings['lesson_video_shown'] : 'BEFORE';
        
        if (!$video_enabled) {
            echo "<!-- LearnDash Video Plugin: Video not enabled for lesson {$lesson_id} -->\n";
            return;
        }
        
        $video_html = $this->generate_video_html($video_url);
        
        // JavaScript DOM injection with console logging
        ?>
        <script>
        console.log('🚀 SCRIPT LOADED! LearnDash Video Plugin is running!');
        console.log('🎥 LearnDash Video Plugin: Starting DOM injection...');
        console.log('📹 Video URL:', <?php echo json_encode($video_url); ?>);
        console.log('⚙️ Video enabled:', <?php echo json_encode($video_enabled); ?>);
        console.log('📍 Video position:', <?php echo json_encode($video_position); ?>);
        
        document.addEventListener('DOMContentLoaded', function() {
            console.log('📄 DOM loaded, searching for injection points...');
            
            // Try multiple injection points for Elementor compatibility
            const injectionPoints = [
                '.elementor-widget-theme-post-content .elementor-widget-container',
                '.entry-content',
                '.learndash-wrapper',
                '.ld-item-content',
                'main',
                'article'
            ];
            
            let injected = false;
            
            for (let selector of injectionPoints) {
                const target = document.querySelector(selector);
                if (target && !injected) {
                    console.log('✅ Found injection point:', selector);
                    
                    const videoContainer = document.createElement('div');
                    videoContainer.innerHTML = <?php echo json_encode($video_html); ?>;
                    videoContainer.style.marginBottom = '20px';
                    
                    <?php if ($video_position === 'BEFORE'): ?>
                    target.insertBefore(videoContainer, target.firstChild);
                    console.log('📺 Video injected BEFORE content');
                    <?php else: ?>
                    target.appendChild(videoContainer);
                    console.log('📺 Video injected AFTER content');
                    <?php endif; ?>
                    
                    injected = true;
                    break;
                }
            }
            
            if (!injected) {
                console.warn('❌ No suitable injection point found. Available elements:');
                injectionPoints.forEach(selector => {
                    const el = document.querySelector(selector);
                    console.log(selector + ':', el ? 'Found' : 'Not found');
                });
            }
        });
        </script>
        <!-- LearnDash Video Plugin: DOM injection script loaded -->
        <?php
    }
    
    /**
     * Get lesson video URL from LearnDash settings
     */
    private function get_lesson_video_url($lesson_id) {
        $lesson_settings = learndash_get_setting($lesson_id);
        
        if (!empty($lesson_settings['lesson_video_url'])) {
            return $lesson_settings['lesson_video_url'];
        }
        
        return '';
    }
    
    /**
     * Generate simple video HTML
     */
    private function generate_video_html($video_url) {
        // Handle YouTube URLs
        if (strpos($video_url, 'youtu.be') !== false || strpos($video_url, 'youtube.com') !== false) {
            preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $video_url, $matches);
            if (!empty($matches[1])) {
                $video_id = $matches[1];
                return '<div class="ld-video"><iframe width="100%" height="600" src="https://www.youtube.com/embed/' . esc_attr($video_id) . '" frameborder="0" allowfullscreen></iframe></div>';
            }
        }
        
        // For other videos, return simple video tag
        return '<div class="ld-video"><video width="100%" height="400" controls><source src="' . esc_url($video_url) . '"></video></div>';
    }
}

// Initialize the plugin
new Lilac_LearnDash_Video_Simple();
}
