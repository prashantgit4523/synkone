@component('mail::layout')

    {{-- Body --}}
    <tr style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 16px; margin: 0;">
        <td class="content-block"
            style="color: #000000; font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 16px; vertical-align: top; margin: 0; padding: 0 0 20px;"
        >
            {{ $data['greeting'] }},
        </td>
    </tr>
    <tr style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 16px; margin: 0;">
        <td class="content-block"
            style="color: #000000; font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 16px; vertical-align: top; margin: 0; padding: 0 0 20px;"
        >
            <p style="color: #000000;">{!! $data['body'] !!}</p>
            @foreach($data['task_lists'] as $task_list)
                <strong style="color: #000000;">Project Name: </strong> {{decodeHTMLEntity($task_list->project->name)}}
                <br/>
                <strong style="color: #000000;">Standard: </strong> {{decodeHTMLEntity($task_list->project->standard)}}
                <br/>
                <br/>
                <strong style="color: #000000;">Control Name: </strong> {{decodeHTMLEntity($task_list->name)}}
                <br/>
                <strong style="color: #000000;">Control ID: </strong> {{$task_list->controlId}}
                <br>
                <hr>
            @endforeach
        </td>
    </tr>
    @if(  array_key_exists("action", $data) )
        @if($data['action']['action_title'])
            <tr style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 16px; margin: 0;">
                <td class="content-block"
                    style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 16px; vertical-align: top; margin: 0; padding: 0 0 20px;"
                >
                    {{ $data['action']['action_title'] }}
                </td>
            </tr>
        @endif
        @if($data['action']['action_button_text'])
            <tr style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 16px; margin: 0;">
                <td class="content-block" itemprop="handler" itemscope
                    itemtype=""
                    style="text-align: center;font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 16px; vertical-align: top; margin: 0; padding: 0 0 20px;"
                >
                    <a href="{{ $data['action']['action_url'] }}" class="btn-primary" itemprop="url"
                    style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 16px; color: #FFF; text-decoration: none; line-height: 2em; font-weight: bold; text-align: center; cursor: pointer; display: inline-block;background-color: #6658dd; margin: 0; border-color: #6658dd; border-style: solid; border-width: 8px 16px;margin-bottom: 16px;">
                    {{ $data['action']['action_button_text'] }}
                    </a>
                </td>
            </tr>
        @endif
    @endif
@endcomponent


