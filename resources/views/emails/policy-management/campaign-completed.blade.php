@component('mail::layout')

    {{-- Body --}}
    <tr style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 16px; margin: 0;">
        <td class="content-block"
            style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 16px; vertical-align: top; margin: 0;"
            valign="top">
            <p style="margin-bottom: 20px;">Hi {{ decodeHTMLEntity($campaign->owner->full_name) }},</p>
            <p><strong>{{ decodeHTMLEntity($campaign->name) }}</strong> policy management campaign created by you on {{ \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $campaign->created_at)->format('d-m-Y h:i A') }} having <strong> below </strong> policy(ies) has been completed.</p>
        </td>
    </tr>
    <tr style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 16px; margin: 0;">
        <td class="content-block"
            style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 16px; vertical-align: top; margin: 0;"
            valign="top">
            <ul>
            @foreach($campaign->policies as $key => $policy)
                @if($key==5)
                    @if($campaign->policies->count()>5)
                        <span> and <strong> {{ $campaign->policies->count() - 5 }} </strong> more...
                    @endif
                    @break
                @endif
                <li>
                    {{decodeHTMLEntity($policy->display_name)}}
                </li>
            @endforeach
            </ul>
        </td>
    </tr>
    <tr style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 16px; margin: 0;">
        <td class="content-block"
            style="text-align: center;font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 16px; vertical-align: top; margin: 0;">
            <a class="btn btn-primary"
               style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 16px; color: #FFF; text-decoration: none; line-height: 2em; font-weight: bold; text-align: center; cursor: pointer; display: inline-block; background-color: #6658dd; margin: 0; border-color: #6658dd; border-style: solid; border-width: 8px 16px;margin-bottom: 16px;"
               href="{{ route("policy-management.campaigns.show", $campaign->id) }}">
               View campaign
            </a>
        </td>
    </tr>
@endcomponent
