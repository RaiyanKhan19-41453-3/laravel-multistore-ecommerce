<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        @php
            try {
                $brandSettings = app(App\Services\SettingsService::class);
                $seoFavicon = $brandSettings->get('store.favicon') ?: $brandSettings->get('store.logo');
                $seoTheme = $brandSettings->get('store.theme_color') ?: '#dd2e44';
                $seoTitle = $brandSettings->get('store.meta_title') ?: ($brandSettings->get('store.name') ?? config('app.name', 'Laravel'));
                $seoDescription = $brandSettings->get('store.meta_description') ?: $brandSettings->get('store.tagline');
                $seoImage = $brandSettings->get('store.og_image') ?: $brandSettings->get('store.logo');
                $googleTagId = $brandSettings->get('marketing.google_tag_id');
                $googleVerification = $brandSettings->get('marketing.google_site_verification');
                $metaPixelId = $brandSettings->get('marketing.meta_pixel_id');
            } catch (Throwable $e) {
                $seoFavicon = $seoTheme = $seoTitle = $seoDescription = $seoImage = null;
                $googleTagId = $googleVerification = $metaPixelId = null;
            }

            // Social crawlers need absolute URLs; settings store site-relative paths.
            $abs = fn ($path) => $path && ! str_starts_with($path, 'http') ? rtrim(config('app.url'), '/').'/'.ltrim($path, '/') : $path;
            $seoFaviconUrl = $abs($seoFavicon) ?? '/favicon.ico';
            $seoImageUrl = $abs($seoImage);
        @endphp

        <title inertia>{{ $seoTitle ?? config('app.name', 'Laravel') }}</title>

        <link rel="icon" href="{{ $seoFaviconUrl }}">
        <meta name="theme-color" content="{{ $seoTheme ?? '#dd2e44' }}">
        @if($seoDescription)<meta name="description" content="{{ $seoDescription }}">@endif

        {{-- Social defaults; pages with their own tags (e.g. products) override these. --}}
        @php($hasOwnShareTags = ($page['component'] ?? '') === 'store/products/show')
        <meta property="og:site_name" content="{{ $seoTitle ?? config('app.name', 'Laravel') }}">
        <meta property="og:type" content="website">
        @if($seoDescription)<meta property="og:description" content="{{ $seoDescription }}">@endif
        @if($seoImageUrl && !$hasOwnShareTags)<meta property="og:image" content="{{ $seoImageUrl }}">@endif
        <meta name="twitter:card" content="summary_large_image">
        @if($seoDescription)<meta name="twitter:description" content="{{ $seoDescription }}">@endif
        @if($seoImageUrl && !$hasOwnShareTags)<meta name="twitter:image" content="{{ $seoImageUrl }}">@endif

        {{-- Search Console ownership verification (strict token format, validated on save). --}}
        @if($googleVerification)<meta name="google-site-verification" content="{{ $googleVerification }}">@endif

        {{-- Google tag: Tag Manager containers and GA4 IDs share the gtag loader. --}}
        @if($googleTagId && str_starts_with($googleTagId, 'GTM-'))
        <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','{{ $googleTagId }}');</script>
        @elseif($googleTagId)
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ $googleTagId }}"></script>
        <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','{{ $googleTagId }}');</script>
        @endif

        {{-- Meta Pixel (numeric ID only, validated on save). --}}
        @if($metaPixelId)
        <script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','{{ $metaPixelId }}');fbq('track','PageView');</script>
        <noscript><img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id={{ $metaPixelId }}&ev=PageView&noscript=1" alt=""></noscript>
        @endif

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

        @routes
        @viteReactRefresh
        @vite(['resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @if(!empty($googleTagId) && str_starts_with($googleTagId, 'GTM-'))
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ $googleTagId }}" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
        @endif
        @inertia
    </body>
</html>
