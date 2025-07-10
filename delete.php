<?php
session_start();
include 'config.php';

if (isset($_POST['delete_feature']) && isset($_POST['id'])) {
    $id = $_POST['id'];
    $collection->deleteOne(['_id' => new MongoDB\BSON\ObjectId($id)]);
    $_SESSION['success'] = "✅ Feature deleted successfully!";
}

header("Location: index.php");
exit;
