//------------------------------------------------------------------------
// Critical JS
//
// Inlined into the page <head> via the threespot/critical/inline_script
// action (see CriticalConfig.php). Write this file for readability —
// the package strips comments and blank lines at render time.
//------------------------------------------------------------------------
(function() {
  var d = document.documentElement;
  var classes = d.className.replace('no-js', 'js');

  // Detect Safari (Mac + iOS). Feature-tests WebKit via the
  // -webkit-appearance style hook, then excludes the other browsers
  // that report a WebKit-style UA on iOS or that historically did so.
  var ua = navigator.userAgent;
  var isWebKit = 'WebkitAppearance' in d.style;
  if (isWebKit && !/Chrome|CriOS|FxiOS|EdgiOS|Android/.test(ua)) {
    classes += ' ua-safari';
  }

  // Flag browsers without ::details-content selector support so the
  // theme can fall back to padding the <details> element directly.
  // Safari < 18.4, Firefox < 139, Chrome < 131.
  if (!CSS.supports('selector(::details-content)')) {
    classes += ' no-details-content-support';
  }

  d.className = classes;
})();
