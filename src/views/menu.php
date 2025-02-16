<!DOCTYPE html>
<html lang="ru">
<head>
    <title>Обработка данных </title>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-rbsA2VBKQhggwzxH7pPCaAqO46MgnOM80zW1RWuH61DGLwZJEdK2Kadq2F9CUG65" crossorigin="anonymous">
</head>

<div class="container">
    <div class="py-5 text-center">
        <h2>Обработать данные xml </h2>
    </div>
    <div class="col-md-8 order-md-1">
        <h4 class="mb-3">Данные для формирования</h4>
    </div>


    <div class="col-md-8 order-md-1">
        <div>
          <h5>  <a href="/parser/get">1. Сформировать список</a> <?= isset($cnt) ? 'Список сформирован. Всего '.$cnt.' файлов' : ''; ?> </h5>
        </div>

        <form action=/parser/parse method="post" enctype="multipart/form-data" class="card p-2" id="form">

            <h5 class="mb-3">2. Поэтапная обработка</h5>
            <div class="row">

                <div class="col-md-8 order-md-1" style="display:<?= isset($isFinished) ? 'block' : 'none' ?>" id="inProcess">
                    <?php if(empty($errors)){ ?>
                        <div class="alert alert-success">
                            <!--Обработка началась, пожалуйста, подождите результата.-->
                             Обработка завершена c <?= $offset ?? ''?> по <?= $len ?? 0?>.

                            <div> <a href='<?= $url?? '' ?> '> Файл с данными в формате XLSX </a></div>
                            <div><a href='<?= $url_csv ?? '' ?> '> Файл с данными в формате csv </a> </div>
                        </div>
                     <?php } else { ?>
                        <div class="alert alert-danger" > <?=$errors[0]; ?> </div>
                    <?php } ?>

                </div>
            </div>
            <div class="row">
                <div class="mb-12">
                    <label for="offset">С какого номера начать  </label>
                    <input type="offset" name="offset" class="form-control" id="offset" placeholder="0" style="width: 10%">
                    <div class="invalid-feedback">

                    </div>
                </div>
            </div>
            <div class="row">
                <div class="mb-12">
                    <label for="len">Сколько файлов взять  </label>
                    <input type="len" name="len" class="form-control" id="len" placeholder="0" style="width: 10%">
                    <div class="invalid-feedback">

                    </div>
                </div>
            </div>

            <div style="padding-left: 100px; padding-top: 30px;">
             <button type="submit" name="created" value="created" class="btn btn-primary">Обработать</button>
                <!--     <a href= "" name="created" value="created" class="btn btn-primary" id="button">Обработать</a>-->
            </div>
        </form>

    </div>
</div>

</body>
</html>

<script>
/*

    button = document.getElementById('button');
    button.addEventListener("click", function() {
        e.preventDefault();
        button.href = '/parser/parse?offset=0&len=45';
        button.click();
        console.log("Кнопка нажата.");
    });
    /*
    document.querySelector('form').onsubmit = async e => {
        e.preventDefault();
        let response = await fetch('/parser/parse', {
            method: 'POST',
            body: new FormData(e.target)
        });
        let result = await response.text();
        console.log(result);
    };
    */

</script>

</script>