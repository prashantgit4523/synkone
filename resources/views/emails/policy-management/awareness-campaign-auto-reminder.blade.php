@component('mail::layout')
    <tr style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 16px; margin: 0;">
        <td class="content-block"
            style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 16px; vertical-align: top; margin: 0;"
        >
            <p style="margin-bottom: 20px;">Hi {{ $user->first_name }} {{ $user->last_name }},</p>
            <p>
                You have been enrolled in a company wide security awareness campaign. Visit the link below to complete the training material.
            </p>
        </td>
    </tr>
    <tr style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 16px; margin: 0;">
        <td class="content-block"
            style="text-align: center;font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 16px; vertical-align: top; margin: 0;"
        >
            <a class="btn btn-primary" style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 16px; color: #FFF; text-decoration: none; line-height: 2em; font-weight: bold; text-align: center; cursor: pointer; display: inline-block; background-color: #6658dd; margin: auto; border-color: #6658dd; border-style: solid; border-width: 8px 16px;" href="{{ route('policy-management.campaigns.acknowledgement.show', $acknowledgmentUserToken->token) }}">View training</a>
        </td>
    </tr>
    <br/>
    <tr style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 16px; margin: 0;">
        <td class="content-block" itemprop="handler" itemscope
            itemtype=""
            style="font-weight: 600;color: #3d4852;font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 16px; vertical-align: top; margin: 0; padding:"
        >
            You must view and complete the training by {{ \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $campaign->due_date, 'UTC')->setTimezone($campaign->timezone)->format('d-m-Y h:i A') }}
        </td>
    </tr>
@endcomponent


