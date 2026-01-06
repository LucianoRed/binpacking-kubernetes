<?php
$dataFile = __DIR__ . '/../data/students.json';

function getStudents() {
    global $dataFile;
    if (!file_exists($dataFile)) {
        return [];
    }
    $json = file_get_contents($dataFile);
    return json_decode($json, true) ?: [];
}

function saveStudents($students) {
    global $dataFile;
    file_put_contents($dataFile, json_encode($students, JSON_PRETTY_PRINT));
}

$message = '';
$search = $_GET['search'] ?? '';

// Handle Registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    $name = $_POST['name'] ?? '';
    $dob = $_POST['dob'] ?? '';
    $year = $_POST['year'] ?? '';

    if ($name && $dob && $year) {
        $students = getStudents();
        $newId = 1;
        if (!empty($students)) {
            $ids = array_column($students, 'id');
            $newId = max($ids) + 1;
        }

        $newStudent = [
            'id' => $newId,
            'name' => $name,
            'dob' => $dob,
            'year' => $year,
            'created_at' => date('Y-m-d H:i:s')
        ];

        $students[] = $newStudent;
        saveStudents($students);
        $message = "Aluno matriculado com sucesso!";
    } else {
        $message = "Por favor, preencha todos os campos.";
    }
}

$students = getStudents();
if ($search) {
    $students = array_filter($students, function ($s) use ($search) {
        return stripos($s['name'], $search) !== false;
    });
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Matrículas</title>
    <style>
        body { font-family: sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        h1, h2 { color: #333; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #f4f4f4; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; }
        input[type="text"], input[type="date"], input[type="number"] { width: 100%; padding: 8px; box-sizing: border-box; }
        button { background-color: #007BFF; color: white; padding: 10px 15px; border: none; cursor: pointer; }
        button:hover { background-color: #0056b3; }
        .message { background-color: #d4edda; color: #155724; padding: 10px; margin-bottom: 20px; border: 1px solid #c3e6cb; }
        .search-box { margin-bottom: 20px; display: flex; gap: 10px; }
    </style>
</head>
<body>

    <h1>Sistema de Matrículas Escolar</h1>

    <?php if ($message): ?>
        <div class="message"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="search-box">
        <form action="" method="GET" style="display: flex; gap: 10px; width: 100%;">
            <input type="text" name="search" placeholder="Buscar aluno por nome..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit">Buscar</button>
            <?php if ($search): ?>
                <a href="?" style="padding: 10px; text-decoration: none; color: #666;">Limpar</a>
            <?php endif; ?>
        </form>
    </div>

    <h2>Nova Matrícula</h2>
    <form action="" method="POST">
        <input type="hidden" name="action" value="register">
        <div class="form-group">
            <label for="name">Nome do Aluno:</label>
            <input type="text" id="name" name="name" required>
        </div>
        <div class="form-group">
            <label for="dob">Data de Nascimento:</label>
            <input type="date" id="dob" name="dob" required>
        </div>
        <div class="form-group">
            <label for="year">Ano Letivo:</label>
            <input type="number" id="year" name="year" value="<?php echo date('Y'); ?>" required>
        </div>
        <button type="submit">Matricular</button>
    </form>

    <h2>Alunos Matriculados</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nome</th>
                <th>Data de Nascimento</th>
                <th>Ano</th>
                <th>Data Matrícula</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($students)): ?>
                <tr><td colspan="5" style="text-align: center;">Nenhum aluno encontrado.</td></tr>
            <?php else: ?>
                <?php foreach ($students as $student): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($student['id']); ?></td>
                        <td><?php echo htmlspecialchars($student['name']); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($student['dob'])); ?></td>
                        <td><?php echo htmlspecialchars($student['year']); ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($student['created_at'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

</body>
</html>
