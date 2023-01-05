@component('mail::layout')
    <tr style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 16px; margin: 0;">
        <td class="content-block"
            style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 16px; vertical-align: top; margin: 0;">
            <p style="margin-bottom: 20px;">Hi {{ decodeHTMLEntity($project->owner->full_name) }},</p>
            <p>
            The third party risk assessment <strong>{{ $project->name }}</strong> created by you on {{ \Carbon\Carbon::parse($project->created_at)->format('d-m-Y h:m A') }} has been completed.<br><br>
            </p>
        </td>
    </tr>
    <tr style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 16px; margin: 0;">
        <td class="content-block"
            style="text-align: center;font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 16px; vertical-align: top; margin: 0;">
            <a class="btn btn-primary"
               style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 16px; color: #FFF; text-decoration: none; line-height: 2em; font-weight: bold; text-align: center; cursor: pointer; display: inline-block; text-transform: capitalize; background-color: #6658dd; margin: 0; border-color: #6658dd; border-style: solid; border-width: 8px 16px;margin-bottom: 16px;"
               href="{{ route('third-party-risk.projects.show', $project->id) }}">
               View assessment
            </a>
        </td>
    </tr>
@endcomponent