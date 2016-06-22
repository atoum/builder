<?php

namespace atoum\builder\controllers\hook;

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
 *     id="Repo",
 *     required="url",
 *     @SWG\Property(name="url", type="string", description="Repository HTTP URL")
 * )
 *
 * @SWG\Model(
 *     id="Head",
 *     required="ref, repo",
 *     @SWG\Property(name="ref", type="string", description="Git reference")
 *     @SWG\Property(name="repo", type="Repo", description="Pull request repository")
 * )
 *
 * @SWG\Model(
 *     id="PullRequest",
 *     required="head",
 *     @SWG\Property(name="head", type="Head", description="Pull request head")
 * )
 *
 * @SWG\Model(
 *     id="PullRequestEvent",
 *     required="number, action, pull_request",
 *     @SWG\Property(
 *         name="number",
 *         type="integer",
 *         description="Pull request number"
 *     ),
 *     @SWG\Property(
 *         name="action",
 *         type="string",
 *         description="Pull request action"
 *     ),
 *     @SWG\Property(
 *         name="pull_request",
 *         type="PullRequest",
 *         description="Resulting commit SHA1"
 *     )
 * )
 */

/**
 * @SWG\Resource(basePath="/")
 */
class pr
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
	 *     path="/hook/pr/{token}",
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
	 *             type="PullRequestEvent",
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

		$integer = [
			'constraints' => [
				new Constraints\NotNull(),
				new Constraints\Type('integer'),
				new Constraints\GreaterThanOrEqual(0)
			]
		];

		$constraint = new Constraints\Collection([
			'allowExtraFields' => true,
			'fields' => [
				'action' => new Constraints\Regex('/^(opened|edited|reopened|synchronize)$/'),
				'number' => new Constraints\Required($integer),
				'pull_request' => new Constraints\Collection([
					'allowExtraFields' => true,
					'fields' => [
						'head' => new Constraints\Collection([
							'allowExtraFields' => true,
							'fields' => [
								'ref' => new Constraints\Regex('#^(?:refs/heads/)?#'),
								'repo' => new Constraints\Collection([
									'allowExtraFields' => true,
									'fields' => [
										'url' => new Constraints\Regex('/^https?:\/\/.+$/')
									]
								])
							]
						])
					]
				])
			]
		]);

		$errors = $this->validator->validate($event, $constraint);

		if ($errors->count() > 0)
		{
			foreach ($errors as $error) {
				$this->logger->warning($error->getPropertyPath() . ' ' . $error->getMessage(), ['actual' => $error->getInvalidValue()]);
			}

			throw new exceptions\validation($errors);
		}

		return new JsonResponse($this->broker->enqueue(build::class, ['pr' => $event]));
	}
}
