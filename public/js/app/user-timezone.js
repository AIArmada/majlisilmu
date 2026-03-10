(() => {
    const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;

    if (!timezone) {
        return;
    }

    document.cookie = `user_timezone=${encodeURIComponent(timezone)}; path=/; max-age=31536000; SameSite=Lax`;
})();
