<?php

if (!function_exists('social_preview')) {
    function social_preview() {
        return resolve(\SocialPreviews\Config::class);
    }
}
