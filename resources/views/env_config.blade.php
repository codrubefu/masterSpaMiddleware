<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>.env configuration</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 2rem; background: #f7f7f7; color: #222; }
        h1 { margin-bottom: 0.5rem; }
        p { margin-top: 0; color: #555; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; background: #fff; }
        th, td { padding: 0.6rem 0.75rem; border: 1px solid #ddd; text-align: left; }
        th { background: #efefef; }
        code { font-family: Consolas, monospace; }
    </style>
</head>
<body>
    <h1>.env configuration</h1>
    <p>Showing key/value pairs from the current <code>.env</code> file (comments and blank lines are omitted).</p>

    @if(empty($configs))
        <p>No configuration values found.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Key</th>
                    <th>Value</th>
                </tr>
            </thead>
            <tbody>
                @foreach($configs as $key => $value)
                    <tr>
                        <td><code>{{ $key }}</code></td>
                        <td>{{ $value }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
