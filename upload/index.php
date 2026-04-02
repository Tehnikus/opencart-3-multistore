<?php
// Bot session prevention
function isBot() {
	if (empty($_SERVER['HTTP_USER_AGENT'])) {
		return true;
	}

	$bots = [
		'bot', 'spider', 'crawl', 'search', 'fetch', 'walk', 'seo', 'scrap', 'news', // Common names
		'meta', 'facebook', 'slurp', 'bing', 'pinterest', 'vk', 'google', 'baidu', 'whatsapp', 'flipboard', 'yahoo', 'nuzzel', // Brands
		'ahrefs','semrush','seranking','mj12','dotbot','linkpad','seokicks','serpstat', 'jetslide', // SEO crawlers
		'ai', 'gpt', 'gemini', 'grok', 'img2dataset', 'perplexity', // AI
		'cotoyogi', 'news-please', // Aggressive unkown bots
		'chrome-lighthouse', 'w3c_validator', // Other
	];

	$userAgent = strtolower($_SERVER['HTTP_USER_AGENT']);

	foreach ($bots as $bot) {
		if (strpos($userAgent, $bot) !== false) {
			return true;
		}
	}

	return false;
}

if (isBot()) {
	// Check if ini_set function is enabled and return safely if not
	if (
		!function_exists('ini_set') || 
		!function_exists('ini_get') || 
		in_array('ini_set', array_map('trim', explode(',', ini_get('disable_functions')))) ||
		!ini_get('open_basedir')
	) {
		return;
	}

	// If core functions are available set cookies
	ini_set('session.use_cookies', '0');
	ini_set('session.use_only_cookies', '0');
	ini_set('session.auto_start', '0');

	// Call only if session is not started yet
	if (session_status() === PHP_SESSION_NONE) {
		// Override session to null if bot detected
		session_set_save_handler(
			function() { return true; },           // open
			function() { return true; },           // close
			function($id) { return ''; },          // read
			function($id, $data) { return true; }, // write
			function($id) { return true; },        // destroy
			function($max) { return true; }        // gc
		);
	}
}
// Version
define('VERSION', '3.0.5.1');

// Configuration
if (is_file('config.php')) {
	/** @phpstan-ignore-next-line requireOnce.fileNotFound */
	require_once('config.php');
}

// Install
if (!defined('DIR_APPLICATION')) {
	header('Location: install/index.php');
	exit;
}

// Startup
require_once(DIR_SYSTEM . 'startup.php');

start('catalog');
