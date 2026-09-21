@if(!empty($openingHours))
<section class="detail-section"><h2>Öffnungszeiten</h2><dl class="opening-hours">@foreach($openingHours as $row)<div @class(['opening-hours__today'=>$row['today']])><dt>{{ $row['day'] }} @if($row['today'])<small>Heute</small>@endif</dt><dd>{{ $row['value'] }}</dd></div>@endforeach</dl></section>
@endif