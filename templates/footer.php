        </main>
        <footer class="app-footer text-center text-muted">
            <p>&copy; <?php echo date("Y"); ?> Tu Empresa. Todos los derechos reservados.</p>
        </footer>
    </div>
</div>

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(() => {
    const toggle = document.querySelector('[data-sidebar-toggle]');
    const backdrop = document.querySelector('[data-sidebar-backdrop]');
    const storageKey = 'facturacion-sidebar-collapsed';
    const isMobile = () => window.matchMedia('(max-width: 767.98px)').matches;

    if (!toggle || !backdrop) {
        return;
    }

    const setCollapsed = (collapsed) => {
        document.body.classList.toggle('app-sidebar-collapsed', collapsed && !isMobile());
        toggle.setAttribute('aria-expanded', String(!collapsed));
        toggle.setAttribute('aria-label', collapsed ? 'Desplegar menú lateral' : 'Plegar menú lateral');
    };

    if (window.localStorage.getItem(storageKey) === 'true') {
        setCollapsed(true);
    }

    toggle.addEventListener('click', () => {
        if (isMobile()) {
            document.body.classList.toggle('app-sidebar-open');
            toggle.setAttribute('aria-expanded', String(document.body.classList.contains('app-sidebar-open')));
            return;
        }

        const collapsed = !document.body.classList.contains('app-sidebar-collapsed');
        setCollapsed(collapsed);
        window.localStorage.setItem(storageKey, String(collapsed));
    });

    backdrop.addEventListener('click', () => {
        document.body.classList.remove('app-sidebar-open');
        toggle.setAttribute('aria-expanded', 'false');
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            document.body.classList.remove('app-sidebar-open');
            toggle.setAttribute('aria-expanded', 'false');
        }
    });

    window.addEventListener('resize', () => {
        if (!isMobile()) {
            document.body.classList.remove('app-sidebar-open');
            setCollapsed(window.localStorage.getItem(storageKey) === 'true');
        } else {
            document.body.classList.remove('app-sidebar-collapsed');
        }
    });
})();
</script>
</body>
</html>