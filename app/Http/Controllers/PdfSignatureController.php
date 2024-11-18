<?php

namespace App\Http\Controllers;

use App\Services\LogService;
use App\Services\PdfSignerService;
use App\Services\ResponseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class PdfSignatureController extends Controller
{
    protected $responseService;
    protected $pdfSignerService;

    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct(
        ResponseService $responseService,
        PdfSignerService $pdfSignerService,
    ) {
        $this->responseService = $responseService;
        $this->pdfSignerService = $pdfSignerService;
    }

    /**
     * Maneja la firma de documentos PDF.
     *
     * @param Request $request La solicitud HTTP que contiene los datos para crear la firma en un documento.
     * @return \Illuminate\Http\JsonResponse La respuesta JSON con la información de la firma.
     */
    public function signPdf(Request $request)
    {
        $logService = new LogService('sign_doc');
        $logService->log("Proceso iniciado", true);
        $request->urlStamp = ($request->urlStamp === null ? "" : $request->urlStamp);

        try {
            // Validar los datos de entrada
            $request->validate([
                'base64PDF' => 'required|string',
                'base64P12' => 'required|string',
                'passP12' => 'required|string',
                'withStamp' => 'required|boolean',
                'urlStamp' => [
                    function ($attribute, $value, $fail) use ($request) {
                        if ($request->input('withStamp') == 1) {
                            if (empty($value) || $value == null) {
                                $fail($attribute . ' is required when withStamp is 1.');
                                return;
                            }

                            if (!preg_match('/^(https?:\/\/)?([a-zA-Z0-9.-]+\.[a-zA-Z]{2,6})(:[0-9]{1,5})?(\/.*)?$/', $value)) {
                                $fail('The format of ' . $attribute . ' is invalid. Please provide a valid URL.');
                            }
                        }
                    },
                ],
                'userStamp' => 'nullable|string',
                'passStamp' => 'nullable|string',
                'visibleSign' => 'required|integer|in:1,2,3',
                'imgSign' => 'nullable|string',
                'posSign' => [
                    function ($attribute, $value, $fail) use ($request) {
                        if (in_array($request->input('visibleSign'), [2, 3]) && empty($value)) {
                            $fail($attribute . ' is required when visibleSign is 2 or 3.');
                        } elseif (in_array($request->input('visibleSign'), [2, 3]) && !empty($value) && !preg_match('/^\d+,\d+,\d+,\d+,\d+$/', $value)) {
                            $fail('The format of ' . $attribute . ' is invalid. It must be pag,x,y,width,height.');
                        }
                    },
                ],
                'graphicSign' => [
                    'nullable',
                    'boolean',
                    function ($attribute, $value, $fail) use ($request) {
                        if ($request->input('visibleSign') == 3 && $value === null) {
                            $fail($attribute . ' is required when visibleSign is 3.');
                        }
                    },
                ],
                'base64GraphicSign' => [
                    function ($attribute, $value, $fail) use ($request) {
                        if ($request->input('graphicSign') && $request->input('visibleSign') == 3 && (empty($value) || $value == "null")) {
                            $fail($attribute . ' is required when graphicSign is true and visibleSign is 3.');
                        }
                    },
                ],
                'backgroundSign' => 'nullable|string',
                'reasonSign' => 'nullable|string',
                'locationSign' => 'nullable|string',
                'infoQR' => [
                    function ($attribute, $value, $fail) use ($request) {
                        if (!empty($request->input('txtQR')) && empty($value)) {
                            $fail($attribute . ' is required when txtQR is not null or empty.');
                        } elseif (!empty($value) && !preg_match('/^\d+,\d+,\d+,\d+$/', $value)) {
                            $fail('The format of ' . $attribute . ' is invalid. It must be pag,x,y,size.');
                        }
                    },
                ],
                'txtQR' => [
                    function ($attribute, $value, $fail) use ($request) {
                        if (!empty($request->input('infoQR')) && empty($value)) {
                            $fail($attribute . ' is required when infoQR is not null or empty.');
                        }
                    },
                ]
            ]);
        } catch (Throwable $th) {
            // Retornar error en caso de falla en la validación
            foreach ($th->errors() as $obj => $value) {
                $logService->log("$obj = " . implode(", ", $value));
            }

            return $this->responseService->error('Validation failed', 400, $th->errors());
        }

        return $this->pdfSignerService->signPdf($request, $logService, $this->responseService);
    }

    public function requestView()
    {
        return view('request');
    }

    public function listRequests()
    {
        $solicitudes = DB::table('solicitud')
            ->join('solicitud_campo', 'solicitud_campo.solicitud_id', '=', 'solicitud.id')
            ->get(); // Obtén todos los registros de la tabla 'solicitud'

        return $this->responseService->success($solicitudes); // Retorna los datos en formato JSON
    }
}
