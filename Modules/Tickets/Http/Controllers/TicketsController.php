<?php
namespace Modules\Tickets\Http\Controllers;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use Modules\Tickets\Models\Ticket;
use Modules\Core\Models\User;
use Modules\Relations\Models\Relation;
use Illuminate\Http\Request;
use Gate;
use Modules\Models\TicketTime;
use Datatables;
use Carbon;
use App\Dinero;
use App\Billy;
use Modules\Models\Integration;
use Modules\Tickets\Requests\Ticket\StoreTicketRequest;
use Modules\Tickets\Requests\Ticket\UpdateTimeTicketRequest;
use Modules\Tickets\Services\Ticket\TicketServiceContract;
use Modules\Core\Services\User\UserServiceContract;
use Modules\Relations\Services\Relation\RelationServiceContract;
use Modules\Core\Services\Setting\SettingServiceContract;
use Modules\Invoices\Services\Invoice\InvoiceServiceContract;

class TicketsController extends Controller
{

  protected $request;
  protected $tickets;
  protected $relations;
  protected $settings;
  protected $users;
  protected $invoices;

  public function __construct(
    TicketServiceContract $tickets,
    UserServiceContract $users,
    RelationServiceContract $relations,
    InvoiceServiceContract $invoices,
    SettingServiceContract $settings
  )
  {
    $this->tickets = $tickets;
    $this->users = $users;
    $this->relations = $relations;
    $this->invoices = $invoices;
    $this->settings = $settings;
    $this->middleware('ticket.create', ['only' => ['create']]);
    $this->middleware('ticket.update.status', ['only' => ['updateStatus']]);
    $this->middleware('ticket.assigned', ['only' => ['updateAssign', 'updateTime']]);
  }

  /**
   * Display a listing of the resource.
   *
   * @return Response
   */
  public function index()
  {
    return view('tickets.index');
  }

  public function anyData()
  {
    $tickets = Ticket::select(
      ['id', 'title', 'created_at', 'deadline', 'fk_staff_id_assign']
    )
      ->where('status', 1)->get();
    return Datatables::of($tickets)
      ->addColumn('titlelink', function ($tickets) {
        return '<a href="tickets/' . $tickets->id . '" ">' . $tickets->title . '</a>';
      })
      ->editColumn('created_at', function ($tickets) {
        return $tickets->created_at ? with(new Carbon($tickets->created_at))
          ->format('d/m/Y') : '';
      })
      ->editColumn('deadline', function ($tickets) {
        return $tickets->created_at ? with(new Carbon($tickets->created_at))
          ->format('d/m/Y') : '';
      })
      ->editColumn('fk_staff_id_assign', function ($tickets) {
        return $tickets->assignee->name;
      })->make(true);
  }


  /**
   * Show the form for creating a new resource.
   *
   * @return Response
   */
  public function create()
  {
    return view('tickets.create')
      ->withUsers($this->users->getAllUsersWithDepartments())
      ->withRelations($this->relations->listAllRelations());
  }

  /**
   * Store a newly created resource in storage.
   *
   * @return Response
   */
  public function store(StoreTicketRequest $request) // uses __contrust request
  {
    $getInsertedId = $this->tickets->create($request);
    return redirect()->route("tickets.show", $getInsertedId);
  }


  /**
   * Display the specified resource.
   *
   * @param  int $id
   * @return Response
   */
  public function show(Request $request, $id)
  {
    $integrationCheck = Integration::first();
    if ($integrationCheck) {
      $api = Integration::getApi('billing');
      $apiConnected = true;
      $invoiceContacts = $api->getContacts();
    } else {
      $apiConnected = false;
      $invoiceContacts = array();
    }
    return view('tickets.show')
      ->withTickets($this->tickets->find($id))
      ->withUsers($this->users->getAllUsersWithDepartments())
      ->withContacts($invoiceContacts)
      ->withTickettimes($this->tickets->getTicketTime($id))
      ->withCompanyname($this->settings->getCompanyName())
      ->withApiconnected($apiConnected);
  }


  /**
   * Sees if the Settings from backend allows all to complete taks
   * or only assigned user. if only assigned user:
   * @param  [Auth]  $id Checks Logged in users id
   * @param  [Model] $ticket->fk_staff_id_assign Checks the id of the user assigned to the ticket
   * If Auth and fk_staff_id allow complete else redirect back if all allowed excute
   * else stmt*/
  public function updateStatus($id, Request $request)
  {
    $this->tickets->updateStatus($id, $request);
    Session()->flash('flash_message', 'Ticket is completed');
    return redirect()->back();
  }


  public function updateAssign($id, Request $request)
  {
    $relationId = $this->tickets->getAssignedRelation($id)->id;
    $this->tickets->updateAssign($id, $request);
    Session()->flash('flash_message', 'New user is assigned');
    return redirect()->back();
  }

  public function updateTime($id, Request $request)
  {
    $this->tickets->updateTime($id, $request);
    Session()->flash('flash_message', 'Time has been updated');
    return redirect()->back();
  }

  public function invoice($id, Request $request)
  {
    $ticket = Ticket::findOrFail($id);
    $relationId = $ticket->relationAssignee()->first()->id;
    $timeTicketId = $ticket->allTime()->get();
    $integrationCheck = Integration::first();
    if ($integrationCheck) {
      $this->tickets->invoice($id, $request);
    }
    $this->invoices->create($relationId, $timeTicketId, $request->all());
    Session()->flash('flash_message', 'Invoice created');
    return redirect()->back();
  }

  /**
   * Remove the specified resource from storage.
   *
   * @param  int $id
   * @return Response
   */
  public function marked()
  {
    Notifynder::readAll(\Auth::id());
    return redirect()->back();
  }


  /**
   * select_all.
   *
   * @return type
   */
  public function select_all()
  {
    if (Input::has('select_all')) {
      $selectall = Input::get('select_all');
      $value = Input::get('submit');
      foreach ($selectall as $delete) {
        $ticket = Ticket::whereId($delete)->first();
        if ($value == 'Delete') {
/*          $ticket->status = 5;
          $ticket->save();*/
        } elseif ($value == 'Close') {
          $ticket->status = 2;
          $ticket->closed = 1;
          $ticket->closed_at = date('Y-m-d H:i:s');
          $ticket->save();
        } elseif ($value == 'Open') {
          $ticket->status = 1;
          $ticket->reopened = 1;
          $ticket->reopened_at = date('Y-m-d H:i:s');
          $ticket->closed = 0;
          $ticket->closed_at = null;
          $ticket->save();
        } elseif ($value == 'Clean up') {
          $thread = Ticket_Thread::where('ticket_id', '=', $ticket->id)->get();
          foreach ($thread as $th_id) {
            // echo $th_id->id." ";
            $attachment = Ticket_attachments::where('thread_id', '=', $th_id->id)->get();
            if (count($attachment)) {
              foreach ($attachment as $a_id) {
                echo $a_id->id.' ';
                $attachment = Ticket_attachments::find($a_id->id);
                $attachment->delete();
              }
              // echo "<br>";
            }
            $thread = Ticket_Thread::find($th_id->id);
            //                        dd($thread);
            $thread->delete();
          }
          $collaborators = Ticket_Collaborator::where('ticket_id', '=', $ticket->id)->get();
          if (count($collaborators)) {
            foreach ($collaborators as $collab_id) {
              echo $collab_id->id;
              $collab = Ticket_Collaborator::find($collab_id->id);
              $collab->delete();
            }
          }
          $tickets = Ticket::find($ticket->id);
          $tickets->delete();
        }
      }
      if ($value == 'Delete') {
        return redirect()->back()->with('success', 'Moved to trash');
      } elseif ($value == 'Close') {
        return redirect()->back()->with('success', 'Tickets has been Closed');
      } elseif ($value == 'Open') {
        return redirect()->back()->with('success', 'Ticket has been Opened');
      } else {
        return redirect()->back()->with('success', Lang::get('lang.hard-delete-success-message'));
      }
    }
    return redirect()->back()->with('fails', 'None Selected!');
  }






}
