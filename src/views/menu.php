<!DOCTYPE html>
<html lang="ru">
<head>
    <title>Обработка данных </title>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-rbsA2VBKQhggwzxH7pPCaAqO46MgnOM80zW1RWuH61DGLwZJEdK2Kadq2F9CUG65" crossorigin="anonymous">
    <link rel="stylesheet" href="style/main.css">
</head>

<div class="container">
    <div class="py-5 text-center">
        <h2>Формирование метаданных на основе DDEX</h2>
    </div>
   
    <div class="col-md-8 order-md-1">
        <form action=/parser/parse method="post" enctype="multipart/form-data" class="card p-2" id="form">
        
            <h5 class="mb-3">Для обработки данных нужно нажать "Обработать"</h5>
            <div class="row">

                <div class="col-md-8 order-md-1" style="display:<?= isset($isFinished) ? 'block' : 'none' ?>" id="inProcess">
                    <?php if(empty($errors)){ ?>
                        <div class="alert alert-success">
                            
                             Обработка завершена c <?= $offset ?? ''?> по <?= $len ?? 0?>.

                            <div> <a href='<?= $url?? '' ?> '> Файл с данными в формате XLSX </a></div>
                            <div><a href='<?= $url_csv ?? '' ?> '> Файл с данными в формате csv </a> </div>
                        </div>
                     <?php } else { ?>
                        <div class="alert alert-danger" > <?=$errors[0]; ?> </div>
                    <?php } ?>

                </div>
            </div>
            <div style="padding-left: 100px; padding-top: 30px; display: flex; gap: 10px;">
                <button type="submit" id="submitBtn" name="created" value="created" class="btn btn-primary">Обработать</button>
                <button type="button" id="cancelBtn" class="btn btn-danger" style="display: none;" onclick="cancelProcess()">Отменить</button>
            </div>
        </form>

        <div id="autoProgressContainer" style="display: none; margin-top: 20px;">
                <div class="progress" style="height: 25px;">
                    <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%;">0%</div>
                </div>
                <div id="autoLog" class="mt-2 text-muted" style="font-family: monospace; font-size: 13px;"></div>
            </div>

            <div id="resultLinks" class="mt-3"></div>

    </div>
</div>

</body>
<script>
let abortController = null;

function cancelProcess() {
    if (abortController) {
        abortController.abort();
        console.log("Процесс отменен пользователем.");
    }
}
document.addEventListener("DOMContentLoaded", function() {
    const form = document.getElementById('form');
    if (!form) return;

    form.addEventListener("submit", async function(e) {
        e.preventDefault();

        const progressContainer = document.getElementById('autoProgressContainer');
        const progressBar = document.getElementById('progressBar');
        const autoLog = document.getElementById('autoLog');
        const resultLinks = document.getElementById('resultLinks');
        const submitBtn = document.getElementById('submitBtn');
        const cancelBtn = document.getElementById('cancelBtn');

        progressContainer.style.display = 'block';
        resultLinks.innerHTML = '';
        submitBtn.disabled = true;
        cancelBtn.style.display = 'inline-block';
        
        progressBar.style.width = '0%';
        progressBar.innerText = '0%';
        progressBar.classList.add('progress-bar-animated', 'progress-bar-striped');
        progressBar.classList.remove('bg-success', 'bg-danger', 'bg-warning');

        abortController = new AbortController();
        const signal = abortController.signal;

        try {
            autoLog.innerText = "Шаг 1/3: Быстрое сканирование директории с диска...";
            let initResponse = await fetch('/parser/ajaxInit', { signal });
            let initData = await initResponse.json();

            if (initData.status !== 'ok') {
                throw new Error(initData.message || "Ошибка инициализации");
            }
            autoLog.innerText = `Шаг 1 завершен. Найдено папок: ${initData.total}`;

            let totalRaw = initData.total;
            let filterOffset = 0;
            let filterBatchSize = 1000;

            while (filterOffset < totalRaw) {
                if (signal.aborted) {
                    throw new Error("Процесс отменен пользователем.");
                }

                autoLog.innerText = `Шаг 2/3: Сканирование папок ${filterOffset}–${Math.min(filterOffset + filterBatchSize, totalRaw)} из ${totalRaw}...`;

                let filterStepResponse = await fetch(`/parser/ajaxFilterStep?offset=${filterOffset}&len=${filterBatchSize}`, { signal });
                let filterStepData = await filterStepResponse.json();

                if (filterStepData.status !== 'ok') {
                    throw new Error(filterStepData.message || "Ошибка при сканировании папок");
                }

                filterOffset = filterStepData.next_offset;

                let scanPercent = totalRaw > 0 ? Math.min(Math.round((filterOffset / totalRaw) * 50), 50) : 50;
                progressBar.style.width = scanPercent + '%';
                progressBar.innerText = scanPercent + '%';
            }

            autoLog.innerText = "Шаг 2/3: Поиск актуальных версий релизов по собранным данным...";
            let filterResponse = await fetch('/parser/ajaxFilterFinalize', { signal });
            let filterData = await filterResponse.json();

            if (filterData.status !== 'ok') {
                throw new Error(filterData.message || "Ошибка при фильтрации");
            }

            let totalFiles = filterData.total;
            autoLog.innerText = `Шаг 2 завершен. Уникальных актуальных релизов к обработке: ${totalFiles}`;

            if (totalFiles === 0) {
                autoLog.innerText = "Нет папок для обработки после фильтрации.";
                return;
            }

            let offset = 0;
            let batchSize = 500; 
            let lastXlsxUrl = '';
            let lastCsvUrl = '';
            let allExcelFiles = [];

            while (offset < totalFiles) {
                if (signal.aborted) {
                    throw new Error("Процесс отменен пользователем.");
                }

                autoLog.innerText = `Шаг 3/3: Обработка пачки: элементы с ${offset} по ${Math.min(offset + batchSize, totalFiles)} из ${totalFiles}...`;

                let stepResponse = await fetch(`/parser/ajaxStep?offset=${offset}&len=${batchSize}`, { signal });
                let stepData = await stepResponse.json();
                console.log("Ответ от шага:", stepData);
                if (stepData.status !== 'ok') {
                    throw new Error(stepData.message || "Ошибка при обработке пачки");
                }

                if (stepData.errors && stepData.errors.length > 0) {
                    console.warn("Предупреждение в пачке:", stepData.errors);
                }

                lastXlsxUrl = stepData.url;
                lastCsvUrl = stepData.url_csv;

                if (stepData.excel_files && stepData.excel_files.length > 0) {
                    allExcelFiles = stepData.excel_files;
                    console.log("Получены файлы Excel:", allExcelFiles);
                }

                offset = stepData.next_offset;

                let percent = 50 + Math.min(Math.round((offset / totalFiles) * 50), 50);
                progressBar.style.width = percent + '%';
                progressBar.innerText = percent + '%';
            }

            autoLog.innerText = "🎉 Обработка всех релизов успешно завершена!";
            progressBar.classList.remove('progress-bar-animated');
            progressBar.classList.add('bg-success');

            let excelLinksHtml = '';
            if (allExcelFiles && allExcelFiles.length > 0) {
                allExcelFiles.forEach((filePath, index) => {
                    let partNum = index + 1;
                    excelLinksHtml += `<div class="mb-1"><a href="/${filePath}" target="_blank">📥 Скачать файл XLSX (Часть ${partNum})</a></div>`;
                });
            } else if (lastXlsxUrl) {
                excelLinksHtml = `<div class="mb-1"><a href="${lastXlsxUrl}" target="_blank">📥 Скачать файл XLSX</a></div>`;
            }

            resultLinks.innerHTML = `
                <div class="alert alert-success mt-3">
                    ${excelLinksHtml}
                    <div class="mt-2"><a href="${lastCsvUrl}" target="_blank">📊 Скачать общий файл CSV</a></div>
                </div>
            `;
        } catch (error) {
            if (error.name === 'AbortError' || error.message.includes("отменен")) {
                autoLog.innerText = "⚠️ Процесс был прерван пользователем.";
                progressBar.classList.add('bg-warning');
            } else {
                autoLog.innerText = `❌ Ошибка: ${error.message}`;
                progressBar.classList.add('bg-danger');
            }
            progressBar.classList.remove('progress-bar-animated');
        } finally {
            submitBtn.disabled = false;
            cancelBtn.style.display = 'none';
            abortController = null;
        }
    });
});
</script>