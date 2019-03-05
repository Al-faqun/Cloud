var my = {};

my.closeDialog = function closeDialog() {
    $("#dialog").fadeOut();
};

my.closeFile = function closeDialog(id) {
    let children = $( id).find( ".selectable" );
    for (let i = 0; i < children.length; i++) {
        my.removeFromSelected(children[i].id);
    }
    $(id).remove();
};

my.removeFromSelected = function (id) {
    let tempSelected = [];
    for (let i = 0; i < selected.length; i++) {
        if (selected[i].id === id) {
            $('#stairs' + id).removeClass('bordered');
            $('#' + id).removeClass('bordered');
        } else {
            tempSelected.push(selected[i]);
        }
    }
    selected = tempSelected;
};

//получить детей выбранной папки
my.getChildren = function (parentID, afterFunction) {
    let errorText;
    $.ajax({
        type: 'get',
        url: Routing.generate('getChildren', {parentID: parentID}),
        data: {},
        success: function (response, status, xhr) {
            if (response.hasOwnProperty("result")) {
                if (response.result === false) {
                    errorText = 'Не удалось загрузить папку: ' + response.errors;
                    alert(errorText);
                } else {
                    //успешно получили html с папкой и её содержимым
                    afterFunction(response.result);
                }
            } else {
                errorText = 'Не удалось загрузить папку, сервер вернул недопустимый ответ';
                alert(errorText);
            }
            
        }
    });
};

//получить данные о папке (название, тип)
my.getFile = function (id, afterFunction) {
    let errorText;
    $.ajax({
        type: 'get',
        url: Routing.generate('getFile', {id: id}),
        data: {},
        success: function (response, status, xhr) {
            if (response.hasOwnProperty("result")) {
                if (response.result === false) {
                    errorText = 'Не удалось загрузить файл: ' + response.errors;
                    alert(errorText);
                } else {
                    //успешно получили html с папкой и её содержимым
                    afterFunction(response.result);
                }
            } else {
                errorText = 'Не удалось загрузить файл, сервер вернул недопустимый ответ';
                alert(errorText);
            }
            
        }
    });
};

//открыть папку и показать рядом
my.openFolder = function (id) {
    if (id !== undefined) {
        //дан айди, ничего не делаем
    } else if (selected.length < 1) {
        alert('Сперва выберите папку!');
    } else if (selected.length > 1) {
        alert('Выберите одну папку!');
    } else if (selected[0].type !== 'dir') {
        alert('Указанный элемент не является папкой!');
    } else {
        id = selected[0].id;
    }
    
    if (id !== undefined) {
        my.getChildren(id, function (html) {
            //удаляем такую же папку, если она существует
            $('#stairs' + id).remove();
            my.removeFromSelected(id);
            $('#stairs-place').append(html);
        });
    }
    
};

//показать окно новой папки
my.newfolderShow = function () {
    if (selected.length < 1) {
        alert('Сперва выберите папку!');
    } else if (selected.length > 1) {
        alert('Выберите одну папку, чтобы создать новую!');
    } else if (selected[0].type !== 'dir') {
        alert('Указанный элемент не является папкой!');
    } else {
        $("#dialog-content").load(Routing.generate('getNewFolderForm'), function () {
            $("#dialog").fadeIn(); //плавное появление блока
        });
    }
    
};

//обработать создание новой папки
my.newFolderProcess = function (event) {
    event.preventDefault();
    event.stopPropagation();
    let errorText, text;
    let parentID = selected[0].id;
    $.ajax({
        type: 'post',
        url: Routing.generate('newFolderProcess', {parentID: parentID}),
        data: $('#newFolderForm').serialize(),
        success: function (response, status, xhr) {
            if (response.hasOwnProperty("result")) {
                if (response.result === false) {
                    errorText = 'Неудача: ' + response.errors;
                    alert(errorText);
                } else {
                    my.getChildren(parentID, function (html) {
                        //удаляем такую же папку, если она существует
                        $('#stairs' + parentID).remove();
                        my.removeFromSelected(parentID);
                        $('#stairs-place').append(html);
                    });
                    text = 'Успешно создали папку';
                    alert(text);
                }
            } else {
                errorText = 'Сервер вернул недопустимый ответ';
                alert(errorText);
            }
        }
    });
    
    return false;
};

//показать окно переименования
my.renameShow = function () {
    if (selected.length < 1) {
        alert('Сперва выберите элемент!');
    } else if (selected.length > 1) {
        alert('Выберите один элемент для переименования!');
    } else {
        $("#dialog-content").load(Routing.generate('getRenameForm', {id: selected[0].id}), function () {
            $("#dialog").fadeIn(); //плавное появление блока
        });
    }
    
};

//обработать создание новой папки
my.renameProcess = function (event) {
    event.preventDefault();
    event.stopPropagation();
    let errorText, text;
    let id = selected[0].id;
    $.ajax({
        type: 'post',
        url: Routing.generate('renameFormProcess', {id: id}),
        data: $('#renameForm').serialize(),
        success: function (response, status, xhr) {
            if (response.hasOwnProperty("result")) {
                if (response.result === false) {
                    errorText = 'Неудача: ' + response.errors;
                    alert(errorText);
                } else {
                    my.getFile(id, function (file) {
                        $('#' + file.id).html(file.name);
                    });
                    text = 'Успешно изменили название файла';
                    alert(text);
                }
            } else {
                errorText = 'Сервер вернул недопустимый ответ';
                alert(errorText);
            }
        }
    });
    
    return false;
};

//удалить выбранные файлы
my.deleteFiles = function () {
    let errorText, text;
    
    if (selected.length === 0) {
        alert('Выберите, что хотите удалить!');
    } else if (confirm('Вы уверены, что хотите удалить эти файлы?')) {
        for (let i = 0; i < selected.length; i++) {
            let id = selected[i].id;
            $.ajax({
                type: 'DELETE',
                url: Routing.generate('deleteFile', {id: id}),
                data: {},
                success: function (response, status, xhr) {
                    if (response.hasOwnProperty("result")) {
                        if (response.result === false) {
                            alert(response.errors);
                        } else {
                            //удалим соответствующий элемент из DOMа
                            $('#' + id).remove();
                            alert('Успешно удалили файл ' + id);
                        }
                    } else {
                        errorText = 'Сервер вернул недопустимый ответ';
                        alert(errorText);
                    }
                }
            });
        }
    }
    
    return false;
};

//открыть папку и показать рядом
my.download = function () {
    if (selected.length < 1) {
        alert('Сперва выберите файл!');
    } else if (selected.length > 1) {
        alert('Выберите один файл!');
    } else if (selected[0].type !== 'file') {
        alert('Указанный элемент не является файлом!');
    } else {
        window.open(Routing.generate('download', {id: selected[0].id}),);
    }
    
};

//показать окно загрузки
my.uploadShow = function () {
    if (selected.length < 1) {
        alert('Сперва выберите папку!');
    } else if (selected.length > 1) {
        alert('Выберите одну папку,  куда будете загружать!');
    } else if (selected[0].type !== 'dir') {
        alert('Указанный элемент не является папкой!');
    } else {
        $("#dialog-content").load(Routing.generate('uploadForm'), function () {
            $("#dialog").fadeIn(); //плавное появление блока
        });
    }
    
};

//обработать загрузку
my.uploadProcess = function (event) {
    event.preventDefault();
    event.stopPropagation();
    let file = document.getElementById('upload_form_file').files[0]; //Files[0] = 1st file
    let reader = new FileReader();
    reader.readAsDataURL(file);
    reader.onload = shipOff;
    
    
    function shipOff(event) {
        let fileData = event.target.result;
        let fileName = $('#upload_form_file').prop('files')[0].name;
        let filenameKey = $("#upload_form_fileName").attr('name');
        let fileKey = $("#upload_form_file").attr('name');
        let csrfKey = $("#upload_form__token").attr('name');
        let errorText, text;
        let parentID = selected[0].id;
        let data = {};
        data[filenameKey] = $('#upload_form_fileName').prop('value').length > 0 ? $('#upload_form_fileName').prop('value') : fileName;
        data[fileKey] = fileData;
        data[csrfKey] = $("#upload_form__token").attr('value');
        
        $.post(Routing.generate('uploadProcess', {parentID: parentID}), data, function (response, status, xhr) {
            if (response.hasOwnProperty("result")) {
                if (response.result === false) {
                    errorText = 'Неудача: ' + response.errors;
                    alert(errorText);
                } else {
                    //закроем старое окно с папкой
                    my.closeFile('stairs' + parentID);
                    my.getChildren(parentID, function (html) {
                        $('#stairs-place').append(html);
                    });
                    text = 'Успешно создали файл!';
                    alert(text);
                }
            } else {
                errorText = 'Сервер вернул недопустимый ответ';
                alert(errorText);
            }
        });
    }
    
    
    return false;
};

//инициализация выбора папок и файлов
var selected = [];
$( document ).ready(function() {
    $(document).on('click', '.selectable', {} ,function(event){
        let id = $(this).attr('id');
        if ($(this).hasClass('bordered')) {
            $(this).removeClass('bordered');
            my.removeFromSelected(id);
        } else {
            //выбрать
            selected.push({id: id, type: $(this).attr('type')});
            $(this).addClass('bordered');
        }
    });
 
    
    
});