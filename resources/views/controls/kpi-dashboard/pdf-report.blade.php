@extends('layouts.pdf-report.layout')

@section('doc_head')
    <style>
        table thead {
            font-size: 22px;
        }
    </style>

@endsection

@section('report-heading', 'KPI Controls')

@section('content')
   
    <!-- TOP CONTROLS TABLE STARTS HERE -->

    <div class="high-effect-risktable">
        <h3 class="text-center pt-5">KPI Controls</h3>
        <table class="table table-striped">
            <caption>Table of controls</caption>
            <thead>
            <tr>
                <th scope="col">Control Id</th>
                <th scope="col">Name</th>
                <th scope="col">Description</th>
                <th scope="col">Current Target (%)</th>
                <th scope="col">Achieved (%)</th>
                <th scope="col">Status</th>
            </tr>
            </thead>

            <tbody>
            @foreach($controls as $control)
                <?php
                    if($control['status']==='Passed'){
                        $class="badge bg-success rounded-pill";
                    }
                    else if($control['status']==='Failed'){
                        $class="badge bg-danger rounded-pill";
                    }
                    else{
                        $class='';
                    }
                ?>
                <tr>
                    <td>{{ $control['controlId'] }}</a></td>
                    <td>{{ $control['name'] }}</td>
                    <td>{{ $control['description'] }}</td>
                    <td>{{ $control['target'] }}</td>
                    <td>{{ $control['per'] }}</td>
                    <td>
                        <span class="{{$class}}">{{ $control['status'] }}</span></td>
                </tr>
            @endforeach
            @if(count($controls) == 0)
                <tr>
                    <td colspan="9" class="text-center">No data found</td>
                </tr>
            @endif
            </tbody>
        </table>
    </div>
@endsection


@section('page_js')
    <!-- apexcharts js -->
    <script type="text/javascript">
        
    </script>
@endsection
