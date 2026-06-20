<?php
if (!defined('ABSPATH')) {
  return;
}

foreach (['validate', 'files', 'csrf', 'template', 'multipage', 'contact', 'schema-client', 'merge-schema'] as $example) {
  require_once __DIR__ . "/{$example}.php";
}

add_action('wp_head', function () {
  ?>
  <style id="schv-examples-css">
  .schv-wrap { max-width: 540px; color: #1a1a1a; }
  .schv-wrap.schv-wide { max-width: 640px; }
  .schv-wrap h2 { font-size: 1.2rem; font-weight: 600; margin: 0 0 .35rem; }
  .schv-wrap h3 { font-size: 1rem; font-weight: 600; margin: 1.25rem 0 .4rem; }
  .schv-desc { font-size: .85rem; color: #6b7280; margin: 0 0 1.1rem; }
  .schv-legend { font-size: .78rem; color: #9ca3af; margin: 0 0 1.1rem; }
  .schv-field { margin: 0 0 1rem; }
  .schv-label { display: block; font-size: .875rem; font-weight: 500; color: #374151; margin-bottom: .25rem; line-height: 1.4; cursor: default; }
  .schv-hint { font-weight: 400; color: #9ca3af; font-size: .78rem; }
  .schv-opt { font-weight: 400; color: #9ca3af; font-size: .78rem; margin-left: .25rem; }
  .schv-req { color: #c00; margin-left: .1rem; font-weight: 600; font-style: normal; }
  .schv-input, .schv-select, .schv-textarea {
    display: block; width: 100%; box-sizing: border-box;
    padding: .45rem .65rem;
    border: 1px solid #d1d5db; border-radius: 5px;
    font-size: .9rem; font-family: inherit; color: #1a1a1a; background: #fff;
    margin-top: .2rem;
    transition: border-color .15s, box-shadow .15s;
    -webkit-appearance: none; appearance: none;
  }
  .schv-select {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 10 10'%3E%3Cpath fill='%236b7280' d='M5 7 0 2h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right .7rem center; padding-right: 2.1rem;
  }
  .schv-input:focus, .schv-select:focus, .schv-textarea:focus {
    outline: none; border-color: #4a6cf7; box-shadow: 0 0 0 3px rgba(74,108,247,.12);
  }
  .schv-input.is-error, .schv-select.is-error, .schv-textarea.is-error { border-color: #c00; }
  .schv-error { display: block; color: #c00; font-size: .8rem; margin-top: .25rem; min-height: 1.1em; }
  .schv-global-error { padding: .55rem .8rem; background: #fef2f2; border-left: 3px solid #c00; border-radius: 3px; color: #c00; font-size: .85rem; margin-bottom: 1rem; }
  .schv-notice { padding: .55rem .8rem; background: #fffbeb; border-left: 3px solid #f59e0b; border-radius: 3px; font-size: .85rem; margin-bottom: 1rem; }
  .schv-success { padding: .55rem .8rem; background: #f0fdf4; border-left: 3px solid #22c55e; border-radius: 3px; color: #15803d; font-size: .85rem; margin-bottom: 1rem; }
  .schv-info { padding: .55rem .8rem; background: #f0f4ff; border-left: 3px solid #4a6cf7; border-radius: 3px; font-size: .85rem; margin-bottom: 1rem; }
  .schv-btn { display: inline-block; padding: .48rem 1.2rem; background: #4a6cf7; color: #fff; border: none; border-radius: 5px; font-size: .9rem; font-weight: 500; cursor: pointer; font-family: inherit; line-height: 1.5; transition: background .15s; }
  .schv-btn:hover { background: #3555e0; }
  .schv-actions { margin-top: 1.25rem; display: flex; align-items: center; gap: .75rem; }
  .schv-back { font-size: .875rem; color: #4a6cf7; text-decoration: none; }
  .schv-back:hover { text-decoration: underline; }
  .schv-dl dt { font-weight: 600; font-size: .875rem; color: #374151; margin-top: .75rem; }
  .schv-dl dd { margin: .15rem 0 0 0; font-size: .9rem; white-space: pre-wrap; }
  </style>
  <?php
});
