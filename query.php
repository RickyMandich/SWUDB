<form action="./query" method="get">
    <input type="text" name="query" id="query" value="<?php if(isset($_GET["query"])) echo $_GET["query"]; else echo "select * from "; ?>">
</form>
<?php
$conn = new mysqli("localhost","swudb","", "my_swudb", 3306);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$resultSet = $conn->query($_GET["query"]);
try{
    $resultSet->fetch_assoc();
    $select=true;
}catch(Error $e){
    $select=false;
}
if($select):
    $resultSet = $conn->query($_GET["query"]);
?>
<table border="">
    <thead>
        <tr>
            <?php foreach ($firstLine = $resultSet->fetch_assoc() as $key => $row): ?>
                <td>
                    <?php echo $key; ?>
                </td>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
        <tr>
            <?php foreach($firstLine as $value): ?>
                <td>
                    <?php echo $value; ?>
                </td>
            <?php endforeach; ?>
        </tr>
        <?php while($line = $resultSet->fetch_assoc()): ?>
            <tr>
                <?php foreach($line as $value): ?>
                    <td>
                        <?php echo $value; ?>
                    </td>
                <?php endforeach; ?>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>
<?php else:
    echo "ho fatto ".$resultSet." modifiche";
    endif;
?>