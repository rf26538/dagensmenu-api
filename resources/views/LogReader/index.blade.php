<!DOCTYPE html>
    <style>
        pre {
            display: block;
            padding: 9.5px;
            margin: 0 0 10px;
            font-size: 13px;
            line-height: 1.42857143;
            color: #333;
            word-break: break-all;
            word-wrap: break-word;
            background-color: #f5f5f5;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        section {
            display: inline-block;
        }
    </style>
    <body>
        <section>
            @if($laravelErrors)
                <h1>Laravel Errors</h1>
                <pre>{{ $laravelErrors }}</pre>
            @endif
            
            @if($badRequestErrors)
                <h1>Bad Request Errors</h1>
                <pre>{{ $badRequestErrors }}</pre>
            @endif

            @if($appErrors)
                <h1>App Errors</h1>
                <pre>{{ $appErrors }}</pre>
            @endif
        </section>
    </body>
</html>