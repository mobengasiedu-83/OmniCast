<?php

namespace App\Enums;

/**
 * The third-party measurement tools an operator may paste a snippet for.
 *
 * Each case owns the wording of its card on the admin screen and a sample of
 * the code the vendor issues, so the paste box can show the shape of the
 * snippet without the platform team having to look it up. The sample is only a
 * placeholder: whatever the operator saves is what the application prints.
 */
enum Integration: string
{
    case GoogleAnalytics = 'google_analytics';
    case GoogleTagManager = 'google_tag_manager';
    case MetaPixel = 'meta_pixel';
    case LinkedInInsight = 'linkedin_insight';
    case HubSpot = 'hubspot';
    case Hotjar = 'hotjar';
    case MicrosoftClarity = 'microsoft_clarity';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::GoogleAnalytics => 'Google Analytics 4',
            self::GoogleTagManager => 'Google Tag Manager',
            self::MetaPixel => 'Meta Pixel',
            self::LinkedInInsight => 'LinkedIn Insight Tag',
            self::HubSpot => 'HubSpot',
            self::Hotjar => 'Hotjar',
            self::MicrosoftClarity => 'Microsoft Clarity',
            self::Custom => 'Custom snippet',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::GoogleAnalytics => 'Measures traffic, campaigns and conversions in GA4.',
            self::GoogleTagManager => 'Loads a Tag Manager container, which can then fire any other tag.',
            self::MetaPixel => 'Reports Facebook and Instagram conversions to Meta Events Manager.',
            self::LinkedInInsight => 'Attributes LinkedIn campaign visits and conversions.',
            self::HubSpot => 'Loads the HubSpot tracking code for campaigns, forms and visitor analytics.',
            self::Hotjar => 'Collects heatmaps, recordings and on-page surveys.',
            self::MicrosoftClarity => 'Free session replay and heatmaps from Microsoft.',
            self::Custom => 'Any other vendor snippet you have been given.',
        };
    }

    /**
     * Where the vendor issues the snippet, or null when there is nothing to link.
     */
    public function docsUrl(): ?string
    {
        return match ($this) {
            self::GoogleAnalytics => 'https://support.google.com/analytics/answer/9304153',
            self::GoogleTagManager => 'https://tagmanager.google.com',
            self::MetaPixel => 'https://business.facebook.com/events_manager2',
            self::LinkedInInsight => 'https://www.linkedin.com/campaignmanager',
            self::HubSpot => 'https://app.hubspot.com',
            self::Hotjar => 'https://insights.hotjar.com',
            self::MicrosoftClarity => 'https://clarity.microsoft.com',
            self::Custom => null,
        };
    }

    /**
     * A sample of the code the vendor issues for the head of the document.
     */
    public function sampleHead(): ?string
    {
        return match ($this) {
            self::GoogleAnalytics => <<<'HTML'
                <script async src="https://www.googletagmanager.com/gtag/js?id=G-XXXXXXXXXX"></script>
                <script>
                  window.dataLayer = window.dataLayer || [];
                  function gtag(){dataLayer.push(arguments);}
                  gtag('js', new Date());
                  gtag('config', 'G-XXXXXXXXXX');
                </script>
                HTML,
            self::GoogleTagManager => <<<'HTML'
                <!-- Google Tag Manager -->
                <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
                new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
                j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
                'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
                })(window,document,'script','dataLayer','GTM-XXXXXXX');</script>
                <!-- End Google Tag Manager -->
                HTML,
            self::MetaPixel => <<<'HTML'
                <script>
                  !function(f,b,e,v,n,t,s)
                  {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
                  n.callMethod.apply(n,arguments):n.queue.push(arguments)};
                  if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
                  n.queue=[];t=b.createElement(e);t.async=!0;
                  t.src=v;s=b.getElementsByTagName(e)[0];
                  s.parentNode.insertBefore(t,s)}(window, document,'script',
                  'https://connect.facebook.net/en_US/fbevents.js');
                  fbq('init', 'PIXEL_ID');
                  fbq('track', 'PageView');
                </script>
                HTML,
            self::LinkedInInsight => <<<'HTML'
                <script type="text/javascript">
                  _linkedin_partner_id = "PARTNER_ID";
                  window._linkedin_data_partner_ids = window._linkedin_data_partner_ids || [];
                  window._linkedin_data_partner_ids.push(_linkedin_partner_id);
                </script>
                <script type="text/javascript">
                  (function(l) {
                    if (!l){window.lintrk = function(a,b){window.lintrk.q.push([a,b])};
                    window.lintrk.q=[]}
                    var s = document.getElementsByTagName("script")[0];
                    var b = document.createElement("script");
                    b.type = "text/javascript";b.async = true;
                    b.src = "https://snap.licdn.com/li.lms-analytics/insight.min.js";
                    s.parentNode.insertBefore(b, s);})(window.lintrk);
                </script>
                HTML,
            self::HubSpot => <<<'HTML'
                <!-- Start of HubSpot Embed Code -->
                <script type="text/javascript" id="hs-script-loader" async defer src="//js.hs-scripts.com/PORTAL_ID.js"></script>
                <!-- End of HubSpot Embed Code -->
                HTML,
            self::Hotjar => <<<'HTML'
                <script>
                    (function(h,o,t,j,a,r){
                        h.hj=h.hj||function(){(h.hj.q=h.hj.q||[]).push(arguments)};
                        h._hjSettings={hjid:SITE_ID,hjsv:6};
                        a=o.getElementsByTagName('head')[0];
                        r=o.createElement('script');r.async=1;
                        r.src=t+h._hjSettings.hjid+j+h._hjSettings.hjsv;
                        a.appendChild(r);
                    })(window,document,'https://static.hotjar.com/c/hotjar-','.js?sv=');
                </script>
                HTML,
            self::MicrosoftClarity => <<<'HTML'
                <script type="text/javascript">
                    (function(c,l,a,r,i,t,y){
                        c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
                        t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
                        y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
                    })(window, document, "clarity", "script", "PROJECT_ID");
                </script>
                HTML,
            self::Custom => null,
        };
    }

    /**
     * A sample of the code the vendor issues for the end of the document.
     */
    public function sampleBody(): ?string
    {
        return match ($this) {
            self::GoogleTagManager => <<<'HTML'
                <!-- Google Tag Manager (noscript) -->
                <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-XXXXXXX"
                height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
                <!-- End Google Tag Manager (noscript) -->
                HTML,
            self::MetaPixel => <<<'HTML'
                <noscript><img height="1" width="1" style="display:none"
                src="https://www.facebook.com/tr?id=PIXEL_ID&ev=PageView&noscript=1"
                /></noscript>
                HTML,
            default => null,
        };
    }
}
