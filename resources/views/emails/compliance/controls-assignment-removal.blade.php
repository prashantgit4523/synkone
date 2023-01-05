@component('mail::layout')

    {{-- Body --}}
    <tr style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 16px; margin: 0;">
        <td class="content-block"
            style=" color:#000000; font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 16px; vertical-align: top; margin: 0; padding: 0 0 20px;"
        >
            {{ $data['greeting'] }},
        </td>
    </tr>
    <tr style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 16px; margin: 0;">
        <td class="content-block"
            style=" color: #000000; font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 16px; vertical-align: top; margin: 0; padding: 0 0 20px;"
        >
            <h3 style="color: #000000 !important; font-size: 16px;font-weight: inherit !important;">{{ decodeHTMLEntity($data['title'])}}</h3>
            <br/>
            <p style="color: #000000 !important;margin-bottom:20px;">
                <strong style="color: #000000 !important;">Project Name: </strong>{{$data['project']->name}}
                <br>
                <strong style="color: #000000 !important;">Standard: </strong>{{ $data['project']->standard}}
            </p>
            @foreach($data['projectControls'] as $key=>  $projectControl)
                <strong style="color: #000000;">Control Name: </strong> {{decodeHTMLEntity($projectControl->name)}}
                <br/>
                <strong style="color: #000000;">Control ID: </strong> {{$projectControl->controlId}}
                <br>
                <hr>
            @endforeach
        </td>
    </tr>
    <tr style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 16px; margin: 0;">
        <td class="content-block"
            style=" color:#000000; font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 16px; vertical-align: top; margin: 0; padding: 0 0 20px;"
        >
            {{ $data['information'] }}
        </td>
    </tr>

@endcomponent

