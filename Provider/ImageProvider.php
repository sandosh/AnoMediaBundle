<?php

namespace Ano\Bundle\MediaBundle\Provider;

use Ano\Bundle\MediaBundle\Model\Media;
use Ano\Bundle\MediaBundle\Util\Image\ImageManipulatorInterface;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Ano\Bundle\SystemBundle\HttpFoundation\File\MimeType\ExtensionGuesser;

class ImageProvider extends AbstractProvider
{
    /* @var \Ano\Bundle\MediaBundle\Util\Image\ImageManipulatorInterface */
    protected $imageManipulator;

    /* @var \Symfony\Component\HttpKernel\Log\LoggerInterface */
    protected $logger;

    /**
     * {@inheritDoc}
     */
    public function prepareMedia(Media $media)
    {
        $this->logger->info('Prepare Media');

        if (null == $media->getUuid()) {
            $uuid = $this->uuidGenerator->generateUuid($media);
            $media->setUuid($uuid);
        }

        $content = $media->getContent();
        if (empty($content)) {
            return;
        }

        if (!$content instanceof File) {
            if (!is_file($content)) {
                throw new \RuntimeException('Invalid image file');
            }

            $media->setContent(new File($content));
        }

        $media->setName($media->getContent()->getBasename());
        $media->setContentType($media->getContent()->getMimeType());
    }

    /**
     * {@inheritDoc}
     */
    public function saveMedia(Media $media)
    {
        if (!$media->getContent() instanceof File) {
            return;
        }

        $originalFile = $this->getOriginalFile($media);
        $originalFile->setContent(file_get_contents($media->getContent()->getRealPath()));
        
        $this->generateFormats($media);
    }

    /**
     * {@inheritDoc}
     */
    public function updateMedia(Media $media)
    {
        $this->saveMedia($media);
    }


    /**
     * {@inheritDoc}
     */
    public function removeMedia(Media $media)
    {
        foreach($this->formats as $format => $options) {
            $path = $this->generateRelativePath($media, $format);
            if ($this->getFilesystem()->has($path)) {
                $this->getFilesystem()->delete($path);
            }
        }
    }

    private function getOriginalFilePath(Media $media)
    {
        return sprintf(
            '%s/%s.%s',
            $this->generatePath($media),
            $media->getUuid(),
            ExtensionGuesser::guess($media->getContentType())
        );
    }

    private function getOriginalFile(Media $media)
    {
        return $this->getFilesystem()->get($this->getOriginalFilePath($media), true);
    }

    public function generateFormats(Media $media)
    {
        $originalFile = $this->getOriginalFile($media);

        foreach($this->formats as $format => $options) {
            $width = array_key_exists('width', $options) ? $options['width'] : null;
            $height = array_key_exists('height', $options) ? $options['height'] : null;

            $this->imageManipulator->resize(
                $media,
                $originalFile,
                $this->filesystem->get($this->generateRelativePath($media, $format), true),
                $width,
                $height
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getMediaUrl(Media $media, $format)
    {
        $path = $this->generateRelativePath($media, $format);

        return $this->cdn->getFullPath($path);
    }

    public function generateRelativePath(Media $media, $format)
    {
        return sprintf(
            '%s/%s_%s.%s',
            $this->generatePath($media),
            $media->getUuid(),
            $format,
            ExtensionGuesser::guess($media->getContentType())
        );
    }

    public function setImageManipulator(ImageManipulatorInterface $imageManipulator)
    {
        $this->imageManipulator = $imageManipulator;
    }

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
}