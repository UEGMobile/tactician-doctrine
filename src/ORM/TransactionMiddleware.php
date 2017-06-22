<?php
namespace League\Tactician\Doctrine\ORM;

use Doctrine\ORM\EntityManagerInterface;
use League\Tactician\Middleware;
use Exception;
use Throwable;

/**
 * Wraps command execution inside a Doctrine ORM transaction
 */
class TransactionMiddleware implements Middleware
{
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Executes the given command and optionally returns a value
     *
     * @param object $command
     * @param callable $next
     * @return mixed
     * @throws Throwable
     * @throws Exception
     */
    public function execute($command, callable $next)
    {
        if(!$this->entityManager->isOpen()){
            // if the entity manager is closed in a previous command, reset the entity manager
            $this->entityManager = $this->entityManager->create(
                $this->entityManager->getConnection(), $this->entityManager->getConfiguration());
        }

        $this->entityManager->beginTransaction();

        try {
            $returnValue = $next($command);

            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (Exception $e) {
            $this->rollbackTransaction();

            throw $e;
        } catch (Throwable $e) {
            $this->rollbackTransaction();

            throw $e;
        }

        return $returnValue;
    }

    /**
     * Rollback the current transaction and close the entity manager when possible.
     */
    protected function rollbackTransaction()
    {
        $this->entityManager->rollback();

        $connection = $this->entityManager->getConnection();
        if (!$connection->isTransactionActive() || $connection->isRollbackOnly()) {
            $this->entityManager->close();
        }
    }
}
