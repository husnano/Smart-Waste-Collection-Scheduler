(function () {
    var container = document.getElementById('scroll-container');
    var navLinks = document.querySelectorAll('.recircle-menu .nav-anchor');
    var sections = document.querySelectorAll('.snap-section');

    if (!container || !sections.length) {
        return;
    }

    // If user opens index.php#about, scroll to that section
    if (window.location.hash) {
        var target = document.querySelector(window.location.hash);
        if (target) {
            setTimeout(function () {
                target.scrollIntoView({ behavior: 'smooth' });
            }, 100);
        }
    }

    navLinks.forEach(function (link) {
        link.addEventListener('click', function (e) {
            var href = link.getAttribute('href');
            if (!href || href.charAt(0) !== '#') {
                return;
            }
            var el = document.querySelector(href);
            if (!el) {
                return;
            }
            e.preventDefault();
            el.scrollIntoView({ behavior: 'smooth' });
            history.replaceState(null, '', href);
        });
    });

    var observer = new IntersectionObserver(
        function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) {
                    return;
                }
                var id = entry.target.getAttribute('id');
                navLinks.forEach(function (link) {
                    link.classList.toggle('active', link.getAttribute('href') === '#' + id);
                });
            });
        },
        { root: container, threshold: 0.55 }
    );

    sections.forEach(function (section) {
        observer.observe(section);
    });
})();
