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
 *     id="RepoOwner",
 *     required="login",
 *     @SWG\Property(name="login", type="string", description="Owner name")
 * )
 *
 * @SWG\Model(
 *     id="BaseRepo",
 *     required="name, owner",
 *     @SWG\Property(name="name", type="string", description="Repository name"),
 *     @SWG\Property(name="owner", type="RepoOwner", description="Repository owner")
 * )
 *
 * @SWG\Model(
 *     id="HeadRepo",
 *     required="clone_url, name, owner",
 *     @SWG\Property(name="clone_url", type="string", description="Repository HTTP URL"),
 *     @SWG\Property(name="owner", type="RepoOwner", description="Repository owner")
 * )
 *
 * @SWG\Model(
 *     id="Base",
 *     required="repo",
 *     @SWG\Property(name="repo", type="BaseRepo", description="Pull request head repository")
 * )
 *
 * @SWG\Model(
 *     id="Head",
 *     required="ref, sha, repo",
 *     @SWG\Property(name="ref", type="string", description="Git reference"),
 *     @SWG\Property(name="sha", type="string", description="Git head SHA1"),
 *     @SWG\Property(name="repo", type="HeadRepo", description="Pull request head repository")
 * )
 *
 * @SWG\Model(
 *     id="PullRequest",
 *     required="head, base",
 *     @SWG\Property(name="head", type="Head", description="Pull request head"),
 *     @SWG\Property(name="base", type="Base", description="Pull request base repository")
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
								'sha' => new Constraints\Regex('#^[0-9a-f]#i'),
								'repo' => new Constraints\Collection([
									'allowExtraFields' => true,
									'fields' => [
										'clone_url' => new Constraints\Regex('/^https?:\/\/.+$/')
									]
								])
							]
						]),
						'base' => new Constraints\Collection([
							'allowExtraFields' => true,
							'fields' => [
								'repo' => new Constraints\Collection([
									'allowExtraFields' => true,
									'fields' => [
										'name' => new Constraints\NotBlank(),
										'owner' => new Constraints\Collection([
											'allowExtraFields' => true,
											'fields' => [
												'login' =>  new Constraints\NotBlank()
											]
										])
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
