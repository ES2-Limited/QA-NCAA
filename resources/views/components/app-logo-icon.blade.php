@php
    $logoUrl = setting('general.logo_url', '/images/ncaa-logo.png');
    $orgName = setting('general.organisation_name', 'Nigeria Civil Aviation Authority');
@endphp
<img src="{{ $logoUrl }}" alt="{{ $orgName }} Logo" {{ $attributes }} />
