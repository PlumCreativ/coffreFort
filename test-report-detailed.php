#!/usr/bin/env php
<?php

/**
 * Script de rapport détaillé pour les tests PHPUnit - Version 2
 * Parse complètement les erreurs ET failures avec status et data
 */

class ConsoleColors {
    const RESET = "\033[0m";
    const RED = "\033[31m";
    const GREEN = "\033[32m";
    const YELLOW = "\033[33m";
    const BLUE = "\033[34m";
    const CYAN = "\033[36m";
    const BOLD = "\033[1m";
}

// Exécuter les tests
echo ConsoleColors::BOLD . "🧪 Exécution des tests...\n" . ConsoleColors::RESET;
$output = [];
$returnCode = 0;
exec('./vendor/bin/phpunit --no-coverage 2>&1', $output, $returnCode);

$fullOutput = implode("\n", $output);

// Initialiser les résultats
$results = [
    'status' => 'success',
    'summary' => [
        'total_tests' => 0,
        'errors' => 0,
        'failures' => 0,
        'passes' => 0,
    ],
    'errors' => [],
    'failures' => [],
];

// Parser la ligne de résumé
if (preg_match('/(\d+) \/ (\d+) \(/', $fullOutput, $matches)) {
    $results['summary']['passes'] = (int)$matches[1];
    $results['summary']['total_tests'] = (int)$matches[2];
}

// Extraire le nombre d'erreurs
if (preg_match('/There were (\d+) errors?:/', $fullOutput, $matches)) {
    $results['summary']['errors'] = (int)$matches[1];
    $results['status'] = 'failed';
}

// Extraire le nombre de failures
if (preg_match('/There were (\d+) failures?:/', $fullOutput, $matches)) {
    $results['summary']['failures'] = (int)$matches[1];
    $results['status'] = 'failed';
}

// Fonction pour parser les blocs numérotés
function parseNumberedBlocks($section) {
    $blocks = [];
    $lines = explode("\n", $section);
    $currentBlock = null;
    $currentNumber = null;
    
    foreach ($lines as $line) {
        if (preg_match('/^(\d+)\) /', $line, $matches)) {
            if ($currentBlock !== null) {
                $blocks[$currentNumber] = $currentBlock;
            }
            $currentNumber = (int)$matches[1];
            $currentBlock = $line . "\n";
        } elseif ($currentBlock !== null) {
            $currentBlock .= $line . "\n";
        }
    }
    
    if ($currentBlock !== null) {
        $blocks[$currentNumber] = $currentBlock;
    }
    
    return $blocks;
}

// Parser les ERREURS
$errorStartPos = strpos($fullOutput, 'There were ' . $results['summary']['errors'] . ' error');
if ($errorStartPos !== false) {
    $failureStartPos = strpos($fullOutput, 'There were ' . $results['summary']['failures'] . ' failure', $errorStartPos);
    if ($failureStartPos === false) {
        $failureStartPos = strpos($fullOutput, '\nTime:', $errorStartPos);
    }
    
    if ($failureStartPos !== false) {
        $errorSection = substr($fullOutput, $errorStartPos, $failureStartPos - $errorStartPos);
    } else {
        $errorSection = substr($fullOutput, $errorStartPos);
    }
    
    // Ligne après "There were..."
    $errorLines = explode("\n", $errorSection);
    array_shift($errorLines); // Enlever la ligne de header
    $errorContent = implode("\n", $errorLines);
    
    $errorBlocks = parseNumberedBlocks($errorContent);
    
    foreach ($errorBlocks as $number => $blockText) {
        // Extraire le test
        if (preg_match('/Tests\\\\Controller\\\\(\w+Test)::(\w+)/', $blockText, $matches)) {
            $testClass = $matches[1];
            $testMethod = $matches[2];
            
            // Extraire le type d'exception
            $exception = 'Exception';
            if (preg_match('/(\w+Exception):/', $blockText, $matches)) {
                $exception = $matches[1];
            }
            
            // Extraire le message
            $message = '';
            if (preg_match('/Exception: (.+?)(?:\n|\/Users)/s', $blockText, $matches)) {
                $message = trim($matches[1]);
            }
            
            $results['errors'][] = [
                'number' => $number,
                'test' => "$testClass::$testMethod",
                'type' => $exception,
                'message' => $message,
                'status' => 500,
                'data' => [
                    'exception_type' => $exception,
                    'description' => $message,
                    'controller' => str_replace('Test', '', $testClass),
                    'test_method' => $testMethod,
                ]
            ];
        }
    }
}

// Parser les FAILURES
$failureStartPos = strpos($fullOutput, 'There were ' . $results['summary']['failures'] . ' failure');
if ($failureStartPos !== false) {
    $timePos = strpos($fullOutput, '\nTime:', $failureStartPos);
    if ($timePos === false) {
        $timePos = strlen($fullOutput);
    }
    
    $failureSection = substr($fullOutput, $failureStartPos, $timePos - $failureStartPos);
    
    // Ligne après "There were..."
    $failureLines = explode("\n", $failureSection);
    array_shift($failureLines);
    $failureContent = implode("\n", $failureLines);
    
    $failureBlocks = parseNumberedBlocks($failureContent);
    
    foreach ($failureBlocks as $number => $blockText) {
        // Extraire le test
        if (preg_match('/Tests\\\\Controller\\\\(\w+Test)::(\w+)/', $blockText, $matches)) {
            $testClass = $matches[1];
            $testMethod = $matches[2];
            
            $statusCode = null;
            $expectedCode = null;
            
            // Parser le pattern "Failed asserting that XXX matches expected YYY"
            if (preg_match('/Failed asserting that (\d+) matches expected (\d+)/', $blockText, $matches)) {
                $statusCode = (int)$matches[1];
                $expectedCode = (int)$matches[2];
            }
            
            $results['failures'][] = [
                'number' => $number,
                'test' => "$testClass::$testMethod",
                'status' => $statusCode,
                'expected' => $expectedCode,
                'data' => [
                    'actual_status' => $statusCode,
                    'expected_status' => $expectedCode,
                    'assertion' => "Status code mismatch",
                    'controller' => str_replace('Test', '', $testClass),
                    'test_method' => $testMethod,
                ]
            ];
        }
    }
}

// Afficher le rapport
echo "\n";
echo str_repeat("═", 80) . "\n";
echo ConsoleColors::BOLD . "📊 RAPPORT DE TESTS DÉTAILLÉ" . ConsoleColors::RESET . "\n";
echo str_repeat("═", 80) . "\n";

// Résumé
echo "\n" . ConsoleColors::BOLD . "📈 RÉSUMÉ" . ConsoleColors::RESET . "\n";
echo "Total: " . $results['summary']['total_tests'] . " tests\n";
echo ConsoleColors::GREEN . "✅ Réussis: " . $results['summary']['passes'] . ConsoleColors::RESET . "\n";
echo ConsoleColors::RED . "❌ Erreurs: " . $results['summary']['errors'] . ConsoleColors::RESET . "\n";
echo ConsoleColors::YELLOW . "⚠️  Failures: " . $results['summary']['failures'] . ConsoleColors::RESET . "\n";

// Erreurs
if (!empty($results['errors'])) {
    echo "\n";
    echo str_repeat("─", 80) . "\n";
    echo ConsoleColors::RED . ConsoleColors::BOLD . "❌ ERREURS (" . count($results['errors']) . ")" . ConsoleColors::RESET . "\n";
    echo str_repeat("─", 80) . "\n";
    
    foreach ($results['errors'] as $error) {
        echo "\n" . ConsoleColors::RED . "Error #{$error['number']}: {$error['test']}" . ConsoleColors::RESET . "\n";
        echo "  Status: " . ConsoleColors::RED . $error['type'] . ConsoleColors::RESET . "\n";
        echo "  Data:\n";
        echo "    - Exception: " . json_encode($error['data']['exception_type']) . "\n";
        echo "    - Description: " . json_encode($error['data']['description']) . "\n";
        echo "    - Controller: " . json_encode($error['data']['controller']) . "\n";
        echo "    - Method: " . json_encode($error['data']['test_method']) . "\n";
    }
}

// Failures
if (!empty($results['failures'])) {
    echo "\n";
    echo str_repeat("─", 80) . "\n";
    echo ConsoleColors::YELLOW . ConsoleColors::BOLD . "⚠️  FAILURES (" . count($results['failures']) . ")" . ConsoleColors::RESET . "\n";
    echo str_repeat("─", 80) . "\n";
    
    foreach ($results['failures'] as $failure) {
        echo "\n" . ConsoleColors::YELLOW . "Failure #{$failure['number']}: {$failure['test']}" . ConsoleColors::RESET . "\n";
        echo "  Status: " . ConsoleColors::YELLOW . $failure['status'] . ConsoleColors::RESET . " → Expected: " . ConsoleColors::GREEN . $failure['expected'] . ConsoleColors::RESET . "\n";
        echo "  Data:\n";
        echo "    - actual_status: " . json_encode($failure['data']['actual_status']) . "\n";
        echo "    - expected_status: " . json_encode($failure['data']['expected_status']) . "\n";
        echo "    - assertion: " . json_encode($failure['data']['assertion']) . "\n";
        echo "    - controller: " . json_encode($failure['data']['controller']) . "\n";
        echo "    - test_method: " . json_encode($failure['data']['test_method']) . "\n";
    }
}

// Statistiques par contrôleur
echo "\n";
echo str_repeat("─", 80) . "\n";
echo ConsoleColors::BLUE . ConsoleColors::BOLD . "📊 STATISTIQUES PAR CONTRÔLEUR" . ConsoleColors::RESET . "\n";
echo str_repeat("─", 80) . "\n";

$stats = [
    'UserController' => 0,
    'FileController' => 0,
    'ShareController' => 0,
    'AdminController' => 0,
];

foreach ($results['errors'] as $error) {
    $controller = $error['data']['controller'];
    if (isset($stats[$controller])) {
        $stats[$controller]++;
    }
}

foreach ($results['failures'] as $failure) {
    $controller = $failure['data']['controller'];
    if (isset($stats[$controller])) {
        $stats[$controller]++;
    }
}

foreach ($stats as $controller => $count) {
    if ($count > 0) {
        echo "  $controller: " . ConsoleColors::RED . "$count issues" . ConsoleColors::RESET . "\n";
    } else {
        echo "  $controller: " . ConsoleColors::GREEN . "✅ OK" . ConsoleColors::RESET . "\n";
    }
}

// Fichier JSON de sortie
echo "\n";
echo str_repeat("─", 80) . "\n";
echo ConsoleColors::BLUE . "💾 Sauvegarde du rapport..." . ConsoleColors::RESET . "\n";

file_put_contents('test_report.json', json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "✅ Rapport JSON: test_report.json (Errors: " . count($results['errors']) . ", Failures: " . count($results['failures']) . ")\n";

echo "\n";
echo str_repeat("═", 80) . "\n";
echo ConsoleColors::BOLD . "Statut: " . ($results['status'] === 'success' ? ConsoleColors::GREEN . "✅ RÉUSSI" : ConsoleColors::RED . "❌ ÉCHOUÉ") . ConsoleColors::RESET . "\n";
echo str_repeat("═", 80) . "\n";

exit($returnCode);
?>
