<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Model Save Logs</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            color: #1f2937;
        }

        h1 {
            margin-bottom: 16px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            border: 1px solid #d1d5db;
            padding: 10px;
            text-align: left;
            vertical-align: top;
        }

        th {
            background-color: #f3f4f6;
        }

        tr:nth-child(even) {
            background-color: #f9fafb;
        }

        .empty {
            padding: 14px;
            border: 1px solid #d1d5db;
            background-color: #f9fafb;
        }
    </style>
</head>
<body>
    <h1>Model Save Logs</h1>

    @if(empty($entries))
        <div class="empty">No model save logs found.</div>
    @else
        <table>
            <thead>
                <tr>
                    <th>Saved At</th>
                    <th>Controller</th>
                    <th>Model</th>
                    <th>Table</th>
                    <th>Primary Key</th>
                    <th>Logged At</th>
                </tr>
            </thead>
            <tbody>
                @foreach($entries as $entry)
                    <tr>
                        <td>{{ $entry['saved_at'] ?? '-' }}</td>
                        <td>{{ $entry['controller'] ?? '-' }}</td>
                        <td>{{ $entry['model'] ?? '-' }}</td>
                        <td>{{ $entry['table'] ?? '-' }}</td>
                        <td>{{ $entry['primary_key'] ?? '-' }}</td>
                        <td>{{ $entry['logged_at'] ?? '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
