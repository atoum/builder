<?php

namespace atoum\builder\controllers;

use atoum\builder\exceptions;
use atoum\builder\resque\broker;
use atoum\builder\resque\jobs\build;
use Psr\Log\LoggerInterface;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Validator\Constraints;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @SWG\Model(
 *     id="Release",
 *     required="tag_name, created_at",
 *     @SWG\Property(name="tag_name", type="string", description="Version number (x.y.z)"),
 *     @SWG\Property(name="created_at", type="string", description="Release date (ISO 8601)")
 * )
 *
 * @SWG\Model(
 *     id="Event",
 *     required="release",
 *     @SWG\Property(
 *         name="release",
 *         type="Release",
 *         description="Release informations"
 *     )
 * )
 */

/**
 * @SWG\Resource(basePath="/")
 */
class hook
{
	/**
	 * @var string
	 */
	private $token;

	/**
	 * @var broker
	 */
	private $broker;

	/**
	 * @var ValidatorInterface
	 */
	private $validator;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	public function __construct($token, broker $broker, ValidatorInterface $validator, LoggerInterface $logger)
	{
		$this->token = $token;
		$this->broker = $broker;
		$this->validator = $validator;
		$this->logger = $logger;
	}

	/**
	 * @SWG\Api(
	 *     path="/hook/{token}",
	 *     @SWG\Operation(
	 *         method="POST",
	 *         @SWG\Consumes("application/json"),
	 *         @SWG\Produces("application/json"),
	 *         @SWG\Parameter(
	 *             paramType="path",
	 *             type="string",
	 *             name="token",
	 *             required=true,
	 *             description="Authentication token"
	 *         ),
	 *         @SWG\Parameter(
	 *             paramType="body",
	 *             type="Event",
	 *             name="body",
	 *             required=true,
	 *             description="Event payload"
	 *         ),
	 *         @SWG\ResponseMessage(
	 *             code=200,
	 *             message="Event acknowledged"
	 *         ),
	 *         @SWG\ResponseMessage(
	 *             code=400,
	 *             message="Invalid event payload"
	 *         ),
	 *         @SWG\ResponseMessage(
	 *             code=403,
	 *             message="Access denied"
	 *         )
	 *     )
	 * )
	 *
	 * @param string  $token
	 * @param Request $request
	 *
	 * @throws AccessDeniedHttpException
	 * @throws exceptions\validation
	 *
	 * @return Response
	 */
	public function __invoke($token, Request $request) : Response
	{
		$event = json_decode($request->getContent(false), true);

		if ($token !== $this->token)
		{
			throw new AccessDeniedHttpException();
		}

		$constraint = new Constraints\Collection([
			'ref' => new Constraints\Regex('/^refs\/head\/.+$/'),
			'after' => new Constraints\Regex('/^[0-9a-f]+$/'),
			'repository' => new Constraints\Collection([
				'url' => new Constraints\Regex('/^https?:\/\/.+$/')
			])
		]);

		$errors = $this->validator->validate($event, $constraint);

		if ($errors->count() > 0)
		{
			foreach ($errors as $error) {
				$this->logger->warning($error->getPropertyPath() . ' ' . $error->getMessage(), ['actual' => $error->getInvalidValue()]);
			}

			throw new exceptions\validation($errors);
		}

		return new JsonResponse($this->broker->enqueue(build::class, ['push' => $event]));
	}
}
