<?php

namespace Nsm\Bundle\ApiBundle\Controller;

use FOS\RestBundle\Controller\Annotations\Delete;
use FOS\RestBundle\Controller\Annotations\Get;
use FOS\RestBundle\Controller\Annotations\Patch;
use FOS\RestBundle\Controller\Annotations\Post;
use FOS\RestBundle\Controller\Annotations\QueryParam;
use FOS\RestBundle\Controller\Annotations\View;
use FOS\RestBundle\Util\Codes;
use Hateoas\Configuration\Route;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Nsm\Bundle\ApiBundle\Entity\Task;
use Nsm\Bundle\ApiBundle\Entity\TaskRepository;
use Nsm\Bundle\ApiBundle\Form\Type\TaskFilterType;
use Nsm\Bundle\ApiBundle\Form\Type\TaskType;
use Nsm\Bundle\CoreBundle\Controller\AbstractController;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Task controller.
 */
class TasksController extends AbstractController
{
    protected $templateGroup = 'NsmApiBundle:Tasks';

    /**
     * @param $id
     *
     * @return mixed
     */
    private function findTaskOr404($id)
    {
        $entity = $this->get('nsm_api.entity.task_repository')->find($id);

        if (!$entity instanceof Task) {
            throw new NotFoundHttpException('Task not found.');
        }

        return $entity;
    }

    /**
     * Browse all Task entities.
     *
     * @Get("/tasks.{_format}", name="task_browse", defaults={"_format"="~"})
     *
     * @View(templateVar="entities", serializerGroups={"task_browse"})
     * @QueryParam(name="page", requirements="\d+", default="1", strict=true, description="Page of the overview.")
     * @QueryParam(name="perPage", requirements="\d+", default="10", strict=true, description="Task count limit")
     * @ApiDoc(
     *  resource=true,
     *  filters={
     *      {"name"="title", "dataType"="string"},
     *      {"name"="project", "dataType"="integer"},
     *      {"name"="page", "dataType"="integer"},
     *      {"name"="perPage", "dataType"="integer"},
     *      {"name"="orderBy", "dataType"="string", "pattern"="(title|createdAt) ASC|DESC"}
     *  })
     */
    public function browseAction(Request $request, $page, $perPage)
    {
        /** @var Form $form */
        $taskSearchForm = $this->createForm(
            new TaskFilterType(),
            array(),
            array(
                'action' => $this->generateUrl('task_browse'),
                'method' => 'GET'
            )
        )->add('search', 'submit');

        $taskSearchForm->handleRequest($request);
        $criteria = $taskSearchForm->getData();

        $qb = $this->get('nsm_api.entity.task_repository')->createQueryBuilder();
        $qb->filterByCriteria($criteria);

        $pager = $this->paginateQuery($qb, $perPage, $page);

        $responseData = array();

        if (true === $this->getViewHandler()->isFormatTemplating($request->getRequestFormat())) {
            $responseData['pager'] = $pager;
            $responseData['search_form'] = $taskSearchForm->createView();
        } else {

            $paginatedCollection = $this->createPaginatedCollection(
                $pager,
                new Route('task_browse', array())
            );

            $responseData = $paginatedCollection;
        }

        $view = $this->view($responseData);
        $view->setTemplate($this->getTemplate($request->query->get('_template', 'browse')));

        return $view;
    }


    /**
     * Finds and displays a Task entity.
     *
     * @Get("/tasks/{id}.{_format}", name="task_read", requirements={"id" = "\d+"}, defaults={"_format"="~"})
     *
     * @View(templateVar="entity", serializerGroups={"task_details"})
     * @ApiDoc(
     *  output="Nsm\Bundle\ApiBundle\Entity\Task"
     * )
     */
    public function readAction($id)
    {
        $entity = $this->findTaskOr404($id);

        return $entity;
    }

    /**
     * Edits an existing Task entity.
     *
     * @Patch("/tasks/{id}", name="task_patch")
     * @Get("/tasks/{id}/edit", name="task_edit")
     *
     * @View()
     * @ApiDoc(
     *  input="Nsm\Bundle\ApiBundle\Form\Type\TaskType",
     *  output="Nsm\Bundle\ApiBundle\Entity\Task"
     * )
     */
    public function editAction(Request $request, $id)
    {
        $entity = $this->findTaskOr404($id);

        /** @var Form $form */
        $form = $this->createForm(
            new TaskType(),
            $entity,
            array(
                'action' => $this->generateUrl('task_patch', array('id' => $entity->getId())),
                'method' => 'PATCH'
            )
        )->add('Update', 'submit');

        $form->handleRequest($request);

        if ($form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($entity);
            $em->flush();

            return $this->redirect(
                $this->generateUrl(
                    'task_read',
                    array(
                        'id' => $entity->getId()
                    )
                )
            );
        }

        return array(
            'entity' => $entity,
            'form' => $form
        );
    }


    /**
     * Creates a add Task entity.
     *
     * @Post("/tasks", name="task_post")
     * @Get("/tasks/add", name="task_add")
     *
     * @QueryParam(name="projectId", requirements="\d+", strict=true, nullable=true, description="The tasks project")
     *
     * @View()
     * @ApiDoc(
     *  input="Nsm\Bundle\ApiBundle\Form\Type\TaskType",
     *  output="Nsm\Bundle\ApiBundle\Entity\Task"
     * )
     */
    public function addAction(Request $request, $projectId)
    {
        $entity = new Task();

        if (null !== $projectId) {
            $project = $this->get('nsm_api.entity.project_repository')->find($projectId);
            $entity->setProject($project);
        }

        /** @var Form $form */
        $form = $this->createForm(
            new TaskType(),
            $entity,
            array(
                'action' => $this->generateUrl('task_post'),
                'method' => 'POST',
                'target_path_choices' => array(
                    'add_task' => 'Add Task'
                )
            )
        )
            ->add('Save', 'submit')
            ->add(
                'Refresh',
                'submit',
                array(
                    'attr' => array(
                        'formnovalidate' => 'formnovalidate'
                    ),
                    'validation_groups' => false
                )
            );

        $form->handleRequest($request);

        if ($form->get('Save')->isClicked() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($entity);
            $em->flush();

            $targetPath = $form->get('_target_path')->getData();

            switch ($targetPath) {
                case 'add_task':
                    $targetPath = $this->generateUrl('task_add', array('projectId' => $entity->getProject()->getId()));
                    break;
                default:
                    $targetPath = $this->generateUrl('task_read', array('id' => $entity->getId()));
            }

            return $this->redirect(
                $targetPath,
                Codes::HTTP_CREATED
            );
        }

        return array(
            'entity' => $entity,
            'form' => $form,
        );
    }

    /**
     * Deletes a Task entity.
     *
     * @Delete("/tasks/{id}", name="task_delete")
     * @Get("/tasks/{id}/destroy", name="task_destroy")
     *
     * @View()
     * @ApiDoc()
     */
    public function destroyAction(Request $request, $id)
    {
        $entity = $this->findTaskOr404($id);

        /** @var Form $form */
        $form = $this->createFormBuilder(array('id' => $id))
            ->add('id', 'hidden')
            ->setAction($this->generateUrl('task_delete', array('id' => $id)))
            ->setMethod('DELETE')
            ->getForm();

        $form->handleRequest($request);

        if ($form->isValid()) {

            $em = $this->getDoctrine()->getManager();
            $em->remove($entity);
            $em->flush();

            if ($this->get('fos_rest.view_handler')->isFormatTemplating($request->getRequestFormat())) {
                return $this->redirect($this->generateUrl('task_browse', array()), Codes::HTTP_OK);
            } else {
                return $this->view(null, Codes::HTTP_NO_CONTENT);
            }
        }

        return array(
            'entity' => $entity,
            'form' => $form
        );

    }

}
