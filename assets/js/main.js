document.addEventListener('DOMContentLoaded', function () {
  const body = document.body;
  const toggle = document.getElementById('sidebarToggle');
  const overlay = document.getElementById('sidebarOverlay');

  function isMobile() {
    return window.matchMedia('(max-width: 991.98px)').matches;
  }

  function closeSidebar() {
    body.classList.remove('sidebar-open');
    if (!isMobile()) {
      body.classList.add('sidebar-collapsed');
    }
  }

  function openSidebar() {
    body.classList.remove('sidebar-collapsed');
    if (isMobile()) {
      body.classList.add('sidebar-open');
    }
  }

  if (toggle) {
    toggle.addEventListener('click', function () {
      if (isMobile()) {
        body.classList.toggle('sidebar-open');
      } else {
        body.classList.toggle('sidebar-collapsed');
      }
    });
  }

  if (overlay) {
    overlay.addEventListener('click', function () {
      body.classList.remove('sidebar-open');
    });
  }

  window.addEventListener('resize', function () {
    if (!isMobile()) {
      body.classList.remove('sidebar-open');
    }
  });

  // Auto-dismiss alerts after 6s
  document.querySelectorAll('.alert-dismissible').forEach(function (el) {
    setTimeout(function () {
      try {
        const alert = bootstrap.Alert.getOrCreateInstance(el);
        alert.close();
      } catch (e) { /* ignore */ }
    }, 6000);
  });
});
