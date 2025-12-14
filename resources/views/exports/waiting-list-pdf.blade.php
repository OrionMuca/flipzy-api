<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Waiting List Export</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .footer {
            margin-top: 30px;
            font-size: 10px;
            color: #666;
            text-align: center;
        }
    </style>
</head>
<body>
    <h1>Waiting List Export</h1>
    
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Company</th>
                <th>Type</th>
                <th>Status</th>
                <th>Signed Up</th>
            </tr>
        </thead>
        <tbody>
            @foreach($entries as $entry)
            <tr>
                <td>{{ $entry['id'] }}</td>
                <td>{{ $entry['name'] }}</td>
                <td>{{ $entry['email'] }}</td>
                <td>{{ $entry['phone'] }}</td>
                <td>{{ $entry['company'] }}</td>
                <td>{{ $entry['type'] }}</td>
                <td>{{ $entry['status'] }}</td>
                <td>{{ $entry['signed_up'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    
    <div class="footer">
        Generated on {{ $generated_at }}
    </div>
</body>
</html>
