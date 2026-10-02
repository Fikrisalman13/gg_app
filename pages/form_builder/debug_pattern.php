<?php
// Mock functions from form.php
function generatePatternFromPlaceholder($p) {
    if (empty($p)) return '';
    $regex = '^';
    $chars = str_split($p);
    $i = 0;
    while ($i < count($chars)) {
        $curr = $chars[$i];
        if (ctype_alpha($curr)) {
            $count = 0;
            while ($i < count($chars) && ctype_alpha($chars[$i])) {
                $count++;
                $i++;
            }
            $regex .= '[A-Za-z]{' . $count . '}';
        } elseif (ctype_digit($curr)) {
            $count = 0;
            while ($i < count($chars) && ctype_digit($chars[$i])) {
                $count++;
                $i++;
            }
            $regex .= '\d{' . $count . '}';
        } else {
            $regex .= preg_quote($curr, '/');
            $i++;
        }
    }
    return $regex . '$';
}

// Test Data
$field = [
    'placeholder' => 'ABC/123', // format
    'visual_placeholder' => 'Enter Data'
];

$fieldFormat = !empty($field['placeholder']) ? $field['placeholder'] : '';
$fieldVisualPlaceholder = !empty($field['visual_placeholder']) ? $field['visual_placeholder'] : '';

$fieldAttr = '';

if (!empty($fieldFormat)) {
    $fieldAttr .= 'pattern="'.htmlspecialchars(generatePatternFromPlaceholder($fieldFormat)).'" ';
    $fieldAttr .= 'minlength="'.strlen($fieldFormat).'" ';
}

if (!empty($fieldVisualPlaceholder)) {
    $fieldAttr .= 'placeholder="'.htmlspecialchars($fieldVisualPlaceholder).'" ';
}

echo "Attribute String: " . $fieldAttr . "\n";
?>
