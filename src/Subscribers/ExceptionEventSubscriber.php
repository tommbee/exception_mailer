<?php

namespace Drupal\exception_mailer\Subscribers;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Drupal\exception_mailer\Utility\UserRepository;
use Drupal\exception_mailer\ExceptionMailer;
use Drupal\Core\Queue\QueueFactory;

/**
 * Subscribe to thrown exceptions to send emails to admin users.
 */
class ExceptionEventSubscriber implements EventSubscriberInterface {

  /**
   * Logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $logger;

  /**
   * The queue service.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * Constructor.
   */
  public function __construct(LoggerChannelFactoryInterface $logger, QueueFactory $queue_factory) {
    $this->logger = $logger;
    $this->queueFactory = $queue_factory;
  }

  /**
   * Event handler.
   *
   * @param Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent $event
   */
  public function onException(GetResponseForExceptionEvent $event) {
    $exception = $event->getException();
    $queue = $this->queueFactory->get('exception_email_queue', TRUE);
    foreach (UserRepository::getUserEmails("administrator") as $admin) {
      $data['email'] = $admin;
      $data['exception'] = get_class($exception);
      $data['message'] = $exception->getMessage();
      $queue->createItem($data);
    }
    $this->logger->get('php')->error($exception->getMessage());
    $response = new Response($exception->getMessage(), 500);
    $event->setResponse($response);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::EXCEPTION][] = ['onException', 60];
    return $events;
  }
}