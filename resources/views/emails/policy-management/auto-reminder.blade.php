@component('mail::layout')
    <tr style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 16px; margin: 0;">
        <td class="content-block"
            style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 16px; vertical-align: top; margin: 0;"
        >
            <p style="margin-bottom: 20px;">Hi {{ $user->first_name }} {{ $user->last_name }},</p>
            <p>
            You have pending policies to acknowledge for the <strong>{{ $campaign->name }}</strong> policy management campaign which is due on {{ \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $campaign->due_date, 'UTC')->setTimezone($campaign->timezone)->format('d-m-Y h:i A') }}.<br><br>
            Please visit the link below to read and acknowledge the following policies:
            </p>
        </td>
    </tr>
    <tr style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 16px; margin: 0;">
        <td class="content-block"
            style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 16px; vertical-align: top; margin: 0;"
        >
            <ol style="line-height: 1.7;">
            @foreach($acknowledgementGroup as $key => $acknowledgement)
                @if($key==5)
                    @if($acknowledgementGroup->count()>5)
                        <span> and <strong> {{ $acknowledgementGroup->count() - 5 }} </strong> more...
                    @endif
                    @break
                @endif
                <li>
                    {{ decodeHTMLEntity($acknowledgement->policy->display_name) }}
                </li>
            @endforeach
            </ol>
        </td>
    </tr>
    <tr style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 16px; margin: 0;">
        <td class="content-block"
            style="text-align: center;font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 16px; vertical-align: top; margin: 0;"
        >
            <a class="btn btn-primary" style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 16px; color: #FFF; text-decoration: none; line-height: 2em; font-weight: bold; text-align: center; cursor: pointer; display: inline-block; background-color: #6658dd; margin-bottom: 16px; margin: auto; border-color: #6658dd; border-style: solid; border-width: 8px 16px;" href="{{ route('policy-management.campaigns.acknowledgement.show', $acknowledgmentUserToken->token) }}">View policies</a>
        </td>
    </tr>
    <br/>
    <tr style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 16px; margin: 0;">
        <td class="content-block" itemprop="handler" itemscope
            itemtype=""
            style="font-weight: 600;color: #3d4852;font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 16px; vertical-align: top; margin: 0; padding:"
        >
            You must read and acknowledge the policy(ies) by {{ \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $campaign->due_date, 'UTC')->setTimezone($campaign->timezone)->format('d-m-Y h:i A') }}
        </td>
    </tr>
@endcomponent


