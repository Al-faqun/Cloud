<?php
namespace App\Controller;

use App\Entity\User;
use App\Filesystem\FS;
use App\Form\NewFolderForm;
use App\Form\UploadForm;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

class MainController extends AbstractController
{
    
    /**
     * Отобразить стартовую страницу
     * @param Request $request
     * @param AuthorizationCheckerInterface $authChecker
     * @param SessionInterface $session
     * @return Response
     */
    public function index(Request $request, AuthorizationCheckerInterface $authChecker, SessionInterface $session)
    {
        
        $errors = [];
        $files = [];
        $homePath = '';
        try {
            if ($authChecker->isGranted(['ROLE_USER'])) {
                $fileSystem = new Filesystem();
                $user = $this->getUser();
                $homePath = FS::conc($this->getParameter('uploaded_dir'), $user->getDirectory());
           
                if (!$fileSystem->exists($homePath)) {
                    try {
                        $fileSystem->mkdir($homePath);
                    } catch (\Throwable $e) {
                        throw new \Exception('Не удалось создать домашнюю директорию пользователя. Обратитесь к администратору.');
                    }
                }
                //если нет сохраненной истории  пользователя - определяем его в home
                $lastID = $session->get('last_id', 0);
                $session->set('last_id', ++$lastID);
                $session->set('0', $homePath);
                
                $files = $this->_getChildren($homePath, $session);
                
            } else {
                $errors[] = 'Вы не авторизованы';
            }
        
        } catch (\Throwable $e) {
            $errors[] = $e->getMessage();
        }
        return $this->render('main.html.twig', [
            'user' => $this->getUser(),
            'files' => $files,
            'errors' => $errors,
            'parentName' => FS::lastname($homePath),
            'parentID' => 0,
            'messages' => []
        ]);
    }
    
    /**
     * Получить по AJAX запрос на детей родителя
     * @param $parentID
     * @param AuthorizationCheckerInterface $authChecker
     * @param SessionInterface $session
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function getChildren($parentID, AuthorizationCheckerInterface $authChecker, SessionInterface $session)
    {
        $errors = [];
        $result = false;
        try {
            if ($authChecker->isGranted(['ROLE_USER'])) {
                $parentFolderPath = $session->get($parentID, null);
                if (is_null($parentFolderPath)) {
                    $errors[] = 'Родительская папка не найдена';
                } else {
                    $files = $this->_getChildren($parentFolderPath, $session);
                    //в ответ посылаем отрендеренный html, чтобы не заниматься этим в жаваскрипте
                    $result = $this->renderView('Main/stairs.html.twig',
                        ['files' => $files, 'parentID' => $parentID, 'parentName' => FS::lastname($parentFolderPath)]
                    );
                }
            } else {
                $errors[] = 'Вы не залогинены';
            }
        } catch (\Throwable $e) {
            $errors[] = 'Непредвиденная ошибка';
        }
        
        $response = [
            'result' => $result ?? false,
            'errors' => implode('. ', $errors)
        ];
        return $this->json($response);
    }
    
    /**
     * Отправить по AJAX данные о файле
     * @param $id
     * @param AuthorizationCheckerInterface $authChecker
     * @param SessionInterface $session
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function getFile($id, AuthorizationCheckerInterface $authChecker, SessionInterface $session)
    {
        $errors = [];
        $result = false;
        try {
            if ($authChecker->isGranted(['ROLE_USER'])) {
                $filePath = $session->get($id, null);
                if (is_null($filePath)) {
                    $errors[] = 'Файл не найден';
                } else {
                    $result = [
                        'name' => FS::lastname($filePath),
                        'id' => $id,
                        'type' => is_dir($filePath) ? 'dir' : 'file'
                    ];
                }
            } else {
                $errors[] = 'Вы не залогинены';
            }
        } catch (\Throwable $e) {
            $errors[] = 'Непредвиденная ошибка';
        }
        
        $response = [
            'result' => $result ?? false,
            'errors' => implode('. ', $errors)
        ];
        return $this->json($response);
    }
    
    /**
     * Отослать по AJAX форму создания новой папки
     * @return Response
     */
    public function newFolderForm()
    {
        $form = $this->createForm(NewFolderForm::class);
        
        return $this->render('Main/new_folder.html.twig', ['form' => $form->createView()]);
    }
    
    /**
     * Обработать по AJAX форму новой папки
     * @param $parentID
     * @param Request $request
     * @param SessionInterface $session
     * @param AuthorizationCheckerInterface $authChecker
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function newFolderProcess($parentID, Request $request, SessionInterface $session, AuthorizationCheckerInterface $authChecker)
    {
        $errors = [];
        $result = false;
        $fileSystem = new Filesystem();
        
        try {
            if ($authChecker->isGranted(['ROLE_USER'])) {
                
                $parentFolderPath = $session->get($parentID, null);
                if (is_null($parentFolderPath) OR !$fileSystem->exists($parentFolderPath) ) {
                    $errors[] = 'Родительская папка не найдена';
                } elseif (!is_dir($parentFolderPath)) {
                    $errors[] = 'Указанный путь не является директорией';
                }  else {
                    $form = $this->createForm(NewFolderForm::class);
                    $form->handleRequest($request);
        
                    if ($form->isSubmitted() && $form->isValid()) {
                        $folderName = $form->get('fileName')->getData();
                        $newFolderPath = FS::conc($parentFolderPath, $folderName);
                        if ($fileSystem->exists($newFolderPath)) {
                            $errors[] = 'Папка уже существует!';
                        } else {
                            try {
                                $fileSystem->mkdir($newFolderPath);
                                $result = true;
                            } catch (\Throwable $e) {
                                $errors[] = 'Не удалось создать папку, ошибка IO';
                            }
                        }
                    } else {
                        $errors[] = 'Ошибка валидации';
                       foreach ($form->getErrors(true, true) as $error) {
                           $errors[] = $error->getMessage();
                       }
                    }
                }
            } else {
                $errors[] = 'Вы не залогинены';
            }
        } catch (\Throwable $e) {
            $errors[] = $e->getMessage();//'Непредвиденная ошибка';
        }
        
        $response = [
            'result' => $result ?? false,
            'errors' => implode('. ', $errors)
        ];
        return $this->json($response);
    }
    
    /**
     * Отослать по AJAX форму создания новой папки
     * @param $id
     * @param SessionInterface $session
     * @return Response
     * @throws \Exception
     */
    public function renameForm($id, SessionInterface $session)
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $form = $this->createForm(NewFolderForm::class);
        //если найдём такой элемент, то вставим его имя в форму для удобства пользователя
        $filePath = $session->get($id, null);
        
        if (!is_null($filePath)) {
            $defaultValue = FS::lastname($filePath);
        } else {
            $defaultValue = '';
        }
        
        return $this->render('Main/rename.html.twig', ['form' => $form->createView(), 'defaultValue' => $defaultValue]);
    }
    
    /**
     * Обработать по AJAX форму новой папки
     * @param $id
     * @param Request $request
     * @param SessionInterface $session
     * @param AuthorizationCheckerInterface $authChecker
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function renameProcess($id, Request $request, SessionInterface $session, AuthorizationCheckerInterface $authChecker)
    {
        $errors = [];
        $result = false;
        $fileSystem = new Filesystem();
        
        try {
            if ($authChecker->isGranted(['ROLE_USER'])) {
                
                $oldFolderPath = $session->get($id, null);
                if (is_null($oldFolderPath) OR !$fileSystem->exists($oldFolderPath) ) {
                    $errors[] = 'Файл не найден';
                }  else {
                    $form = $this->createForm(NewFolderForm::class);
                    $form->handleRequest($request);
                    
                    if ($form->isSubmitted() && $form->isValid()) {
                        $folderName = $form->get('fileName')->getData();
                        $newFolderPath = FS::replace($oldFolderPath, $folderName);
                        if ($fileSystem->exists($newFolderPath)) {
                            $errors[] = 'Файл с таким именем уже существует!';
                        } else {
                            try {
                                $fileSystem->rename($oldFolderPath, $newFolderPath);
                                //не забудем перезаписать новое название файла
                                $session->set($id, $newFolderPath);
                                $result = true;
                            } catch (\Throwable $e) {
                                $errors[] = 'Не изменить название файла';
                            }
                        }
                        
                    } else {
                        $errors[] = 'Ошибка валидации';
                        foreach ($form->getErrors(true, true) as $error) {
                            $errors[] = $error->getMessage();
                        }
                    }
                }
            } else {
                $errors[] = 'Вы не залогинены';
            }
        } catch (\Throwable $e) {
            $errors[] = $e->getMessage();//'Непредвиденная ошибка';
        }
        
        $response = [
            'result' => $result ?? false,
            'errors' => implode('. ', $errors)
        ];
        return $this->json($response);
    }
    
    /**
     * Удалить файл по AJAX
     * @param $id
     * @param SessionInterface $session
     * @param AuthorizationCheckerInterface $authChecker
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function deleteFile($id, SessionInterface $session, AuthorizationCheckerInterface $authChecker)
    {
        $errors = [];
        $result = false;
        $fileSystem = new Filesystem();
        
        try {
            if ($authChecker->isGranted(['ROLE_USER'])) {
                $filePath = $session->get($id, null);
                if (is_null($filePath) OR !$fileSystem->exists($filePath) ) {
                    $errors[] = 'Файл не найден';
                }  else {
                    $fileSystem->remove($filePath);
                    $result = true;
                }
            } else {
                $errors[] = 'Вы не залогинены';
            }
        } catch (\Throwable $e) {
            $errors[] = $e->getMessage();//'Непредвиденная ошибка';
        }
        
        $response = [
            'result' => $result ?? false,
            'errors' => implode('. ', $errors)
        ];
        return $this->json($response);
    }
    
    /**
     * Загрузить файл в этой вкладке
     * @param $id
     * @param SessionInterface $session
     * @param AuthorizationCheckerInterface $authChecker
     * @return BinaryFileResponse|Response
     */
    public function downloadFile($id, SessionInterface $session, AuthorizationCheckerInterface $authChecker)
    {
        $errors = [];
        $result = false;
        $fileSystem = new Filesystem();
        
        try {
            if ($authChecker->isGranted(['ROLE_USER'])) {
                $filePath = $session->get($id, null);
                if (is_null($filePath) OR !$fileSystem->exists($filePath) ) {
                    $errors[] = 'Файл не найден';
                }elseif (is_dir($filePath)) {
                    $errors[] = 'Невозможно загрузить папку';
                } else {
                    //предлагаем скачать файл
                    $response = new BinaryFileResponse($filePath);
                    $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT);
    
                    return $response;
                }
            } else {
                $errors[] = 'Вы не залогинены';
            }
        } catch (\Throwable $e) {
            $errors[] = $e->getMessage();//'Непредвиденная ошибка';
        }
       
        return $this->render('main.html.twig', [
            'user' => $this->getUser(),
            'errors' => $errors,
            'messages' => []
        ]);
    }
    
    /**
     * Отослать по AJAX форму для загрузки
     * @return Response
     */
    public function uploadForm()
    {
        $form = $this->createForm(UploadForm::class);
    
        return $this->render('Main/upload.html.twig', ['form' => $form->createView()]);
    }
    
    /**
     * Обработать по AJAX загруженный файл
     * @param $parentID
     * @param Request $request
     * @param AuthorizationCheckerInterface $authChecker
     * @param SessionInterface $session
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function uploadProcess($parentID, Request $request, AuthorizationCheckerInterface $authChecker, SessionInterface $session)
    {
        $fileSystem = new Filesystem();
        $errors = [];
        $result = false;
        
        if ($authChecker->isGranted(['ROLE_USER'])) {
            /** @var User $user */
            $user = $this->getUser();
            //сперва проверяем параметр родительской папки
            $directoryPath = $session->get($parentID, null);
            if (is_null($directoryPath) OR !$fileSystem->exists($directoryPath)) {
                $errors[] = 'Родительская папка не найдена';
            } elseif (!is_dir($directoryPath)) {
                $errors[] = 'Указанный путь не является директорией';
            } else {
                //если родитель в порядке, проверяем файл
                $form = $this->createForm(UploadForm::class);
                $form->handleRequest($request);
                $base64DataString =  $request->request->get('file64', 'NULL');
                if (is_null($base64DataString)) {
                    $errors[] = 'Выне загрузили файл';
                } else {
                    if ($form->isSubmitted() && $form->isValid()) {
                        list($dataType, $imageData) = explode(';', $base64DataString);
                        $fileExtension = explode('/', $dataType)[1];
                        list(, $encodedFileData) = explode(',', $imageData);
                        $decodedFileData = base64_decode($encodedFileData);
                        
                        /** @var UploadedFile $file */
                        $fileName = $form->get('fileName')->getData();
                        $filePath = FS::conc($directoryPath, $fileName);
                    
                        if ($fileSystem->exists($filePath)) {
                            $errors[] = 'Файл уже существует!';
                        } else {
                            try {
                                $result = true;
                                file_put_contents($filePath, $decodedFileData);
                            } catch (\Throwable $e) {
                                //файл удаляется PHP автоматически
                                $errors[] = $e->getMessage();
                                $result = false;
                            }
                        }
                        
                    } else {
                        $errors[] = 'Ошибка валидации';
                        foreach ($form->getErrors(true, true) as $error) {
                            $errors[] = $error->getMessage();
                        }
                    }
                }
            }
        } else {
            $errors[] = 'Вы не залогинены';
        }
        return $this->json([
            'result' => $result,
            'errors' => $errors,
        ]);
        
    }
    
    private function _getChildren($parentPath, SessionInterface $session)
    {
        $finder = new Finder();
        $finder->depth('< 1')->in($parentPath);
    
        $files = [];
        foreach ($finder as $file) {
            //получаем айди файла, увеличиваем и сохраняем
            $lastID = $session->get('last_id');
            $session->set('last_id', ++$lastID);
            //сохраним путь к файлу в сессии, чтобы позже иметь к нему доступ
            //пользователь получит доступ только к тем файлам, о которых ему говорилось до этого
            $session->set($lastID, $file->getPathname());
        
            $files[FS::lastname($parentPath)][] = [
                'name' => $file->getFilename(),
                'id' => $lastID,
                'type' => $file->getType()
            ];
        }
        
        return $files;
    }
    
  
    
}


