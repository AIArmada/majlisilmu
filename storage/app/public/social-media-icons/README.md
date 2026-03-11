# Social Media Icons

Put all social media SVG icons in this folder:

- `storage/app/public/social-media-icons/`

The app will load icons by platform name using this pattern:

- `<platform>.svg`

Examples:

- `facebook.svg`
- `twitter.svg`
- `instagram.svg`
- `youtube.svg`
- `tiktok.svg`
- `telegram.svg`
- `whatsapp.svg`
- `linkedin.svg`
- `website.svg`
- `other.svg`
- `link.svg` (fallback when platform is empty)

Notes:

- If user input is `x`, it is normalized to `twitter`, so the icon file should be `twitter.svg`.
- Public URL path used by the app: `/storage/social-media-icons/<platform>.svg`
