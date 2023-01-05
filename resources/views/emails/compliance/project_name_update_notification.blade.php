@component('mail::layout')

    {{-- Body --}}
    <tr style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 16px; margin: 0;">
        <td class="content-block"
            style=" color:#000000; font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 16px; vertical-align: top; margin: 0;"
        >
            {{ $data['greeting'] }},
            <br/>
            <br/>
        </td>
    </tr>
    <br/>
    <tr style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 16px; margin: 0;">
        <td class="content-block"
            style=" color: #000000; font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 16px; vertical-align: top; margin: 0;"
        >
            <h3 style="color: #000000 !important; font-size: 16px;font-weight: inherit !important; margin-bottom: 0;">{!! $data['title'] !!}</h3>
            <br/>
            <p style="margin-bottom:20px; color: #000000;">
                <strong style="color: #000000;">Project Name: </strong>{{decodeHTMLEntity($data['projectName'])}}
                <br/>
                <strong style="color: #000000;">Standard: </strong>{{decodeHTMLEntity($data['standard'])}}
            </p>
            @foreach($data['projectControls'] as $key=>  $projectControl)
                <strong style="color: #000000;">Control Name: </strong> {{ decodeHTMLEntity($projectControl->name) }}
                <br/>
                <strong style="color: #000000;">Control ID: </strong> {{ $projectControl->controlId }}
                <br>
                <hr>
            @endforeach
        </td>
    </tr>

@endcomponent



