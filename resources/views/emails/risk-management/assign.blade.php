@component('mail::layout')
    <tr style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 16px; margin: 0;">
        <td class="content-block"
            style=" color:#000000; font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 16px; vertical-align: top; margin: 0; padding: 0 0 20px;"
            valign="top">
            {{ $data['greeting'] }},
        </td>
    </tr>
    <tr style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 16px; margin: 0;">
        <td class="content-block"
            style=" color: #000000; font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 16px; vertical-align: top; margin: 0; padding: 0 0 20px;"
            valign="top">
            <h3 style="color: #000000 !important; font-size: 16px;font-weight: inherit !important;">{{ $data['title'] }}</h3>
            <p style="color: #000000 !important;margin-bottom:20px;">
                <b style="color: #000000 !important;">Risk Name: </b> {{ decodeHTMLEntity($data['risk']->name) }}
                <br>
                <b style="color: #000000 !important;">Risk Description: </b> {{ decodeHTMLEntity($data['risk']->risk_description) }}
                <br>
                <b style="color: #000000 !important;">Control: </b>{{ decodeHTMLEntity($data['mappedControls']) ? decodeHTMLEntity($data['mappedControls']->name) : "No control has been assigned" }}
                <br>
                <b style="color: #000000 !important;">Risk Treatment: </b>{{decodeHTMLEntity($data['risk']->treatment)}}
                <br>
                <b style="color: #000000 !important;">Status: </b>{{decodeHTMLEntity($data['risk']->status) === 'Close' ? "Closed" : decodeHTMLEntity($data['risk']->status) }}
            </p>
        </td>
    </tr>
    <td class="content-block"
        style="color:#000000;font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 16px; vertical-align: top; margin: 0; padding: 0 0 20px;"
        valign="top">
        No further action is needed for the time being.
    </td>
    <tr style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 16px; margin: 0;">
        <td class="content-block"
            style="text-align: center;font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 16px; vertical-align: top; margin: 0;">
            <a class="btn btn-primary"
               style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 16px; color: #FFF; text-decoration: none; line-height: 2em; font-weight: bold; text-align: center; cursor: pointer; display: inline-block; text-transform: capitalize; background-color: #6658dd; margin: 0; border-color: #6658dd; border-style: solid; border-width: 8px 16px;margin-bottom: 16px;"
               href="{{ route('risks.projects.project-show', ['project' => $data['risk']->project->id, 'risk' => $data['risk']->id]) }}">
               View risk
            </a>
        </td>
    </tr>
@endcomponent
