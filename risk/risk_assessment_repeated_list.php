<?php
/*
 * 위험성평가 수시평가 - 목록
 */
require_once "../../lib/include.php";
require_once "../common/safe_ini.php";

// //세션 만료일 경우
// if (!isset($_SESSION["user"]["uno"])) {
//     echo json_encode(array("session_out" => true));
//     exit();
// }

$mode = $_POST["mode"];
$jno = $_POST["jno"];

// 결재자 체크
if("APPROVER" == $mode) {
    $assessmentId = $_POST["assessmentId"];
    $auth = $_POST["auth"];
    $status = '';

    if($auth == "SUPERVISOR") {
        $status = '10';
    } else if ($auth == "SAFETY_MANAGER") {
        $status = '20';
    } else if ($auth == "SUPERINTENDENT") {
        $status = '30';
    } else {
        $status = '50';
    }

    $params = array();
    $SQL = "SELECT *
            FROM RISK_APPROVAL_TARGET T
            INNER JOIN RISK_ASSESSMENT_INFO I ON T.ASSESSMENT_ID = I.ASSESSMENT_ID
            RIGHT OUTER JOIN JOB_MANAGER M ON I.JNO = M.JNO
            WHERE T.ASSESSMENT_ID = :assessmentId
            AND M.AUTH = :auth
            AND T.APPROVAL_STATUS = :approval_status";
    $params = array(
        ":assessmentId" => $assessmentId,
        ":auth" => $auth,
        ":approval_status" => $status
    );
    $db->query($SQL, $params);
    $cnt = $db->nf();

    $result = array(
        "cnt" => $cnt
    );

    echo json_encode($result);
}
//삭제
else if ("DEL" == $mode) {
    $assessmentId = $_POST["assessmentId"];

    $isExecute = true;
    $proceed = false;
    $SQL  = "SELECT COUNT(ASSESSMENT_ITEM_ID) AS CNT ";
    $SQL .= "FROM RISK_ASSESSMENT_DETAIL ";
    $SQL .= "WHERE ASSESSMENT_ID = :assessmentId ";
    $params = array(
        ":assessmentId" => $assessmentId
    );
    $db->query($SQL, $params);
    $db->next_record();
    $row = $db->Record;
    if ($row["cnt"] > 0) {
        $msg = "작성 중인 위험성 평가는 삭제할 수 없습니다.";
        $isExecute = false;
    }
    else {
        $proceed = true;
    }

    if ($proceed) {
        $SQL  = "DELETE FROM RISK_APPROVAL_TARGET ";
        $SQL .= "WHERE ASSESSMENT_ID = :assessmentId ";
        $params = array(
            ":assessmentId" => $assessmentId
        );
        if ($db->query($SQL, $params)) {
            $proceed = true;
        }
        else {
            $proceed = false;
            $msg = "삭제 실패하였습니다.";
        }
    }

    if ($proceed) {
        $SQL  = "DELETE FROM RISK_ASSESSMENT_INFO ";
        $SQL .= "WHERE ASSESSMENT_ID = :assessmentId ";
        $params = array(
            ":assessmentId" => $assessmentId
        );
        if ($db->query($SQL, $params)) {
            $proceed = true;
            $msg = "삭제되었습니다.";
        }
        else {
            $proceed = false;
            $msg = "삭제 실패하였습니다.";
        }
    }

    $result = array(
        "isExecute" => $isExecute,
        'msg' => $msg
    );

    echo json_encode($result);
}
//저장
else if ("SAVE" == $mode) {
    $assessmentId = $_POST["assessmentId"];
    $replaceWithFile = $_POST["chkReplaceWithFile"];
    $scheduledMeetingDate = $_POST["scheduledMeetingDate"];
    $startDate = $_POST["startDate"];
    $endDate = $_POST["endDate"];
    $deadline = $_POST["deadline"];
    $cnoList = $_POST["cno"];

    if (empty($assessmentId)) {
        $SQL  = "WITH T_S AS ( ";
        $SQL .= "SELECT NVL(MAX(SEQ), 0) + 1 AS SEQ ";
        $SQL .= "FROM RISK_ASSESSMENT_INFO ";
        $SQL .= "WHERE JNO = :jno ";
        $SQL .= " AND ASSESSMENT_TYPE = 'ASMT_REP' ";
        $SQL .= ") ";
        $SQL .= "SELECT SEQ_ASSESSMENT_ID.NEXTVAL AS ASSESSMENT_ID, T_S.SEQ ";
        $SQL .= "FROM T_S ";
        $params = array(
            ":jno" => $jno
        );
        $db->query($SQL, $params);
        $db->next_record();
        $row = $db->Record;
        $assessmentId = $row["assessment_id"];
        $seq = $row["seq"];
    }
    if ("Y" == $replaceWithFile) {
        $scheduledMeetingDate = "";
        $deadline = "";
        $seq = $_POST["seq"];
    }

    $proceed = false;
    $SQL  = "MERGE INTO RISK_ASSESSMENT_INFO ";
    $SQL .= "USING DUAL ";
    $SQL .= "ON (ASSESSMENT_ID = :assessmentId) ";
    $SQL .= "WHEN MATCHED THEN ";
    $SQL .= " UPDATE SET SCHEDULED_MEETING_DATE = TO_DATE(:scheduledMeetingDate, 'YYYY-MM-DD'), START_DATE = TO_DATE(:startDate, 'YYYY-MM-DD'), END_DATE = TO_DATE(:endDate, 'YYYY-MM-DD'), ";
    $SQL .= "  DEADLINE = TO_DATE(:deadline, 'YYYY-MM-DD'), MOD_USER = :modUser, MOD_DATE = SYSTIMESTAMP ";
    $SQL .= "WHEN NOT MATCHED THEN ";
    $SQL .= " INSERT (ASSESSMENT_ID, JNO, SEQ, ASSESSMENT_TYPE, REPLACE_WITH_FILE, SCHEDULED_MEETING_DATE, START_DATE, END_DATE, DEADLINE, REG_USER, REG_DATE, MOD_USER, MOD_DATE) ";
    $SQL .= " VALUES (:assessmentId, :jno, :seq, :assessmentType, :replaceWithFile, TO_DATE(:scheduledMeetingDate, 'YYYY-MM-DD'), TO_DATE(:startDate, 'YYYY-MM-DD'), TO_DATE(:endDate, 'YYYY-MM-DD'), TO_DATE(:deadline, 'YYYY-MM-DD'), :regUser, SYSTIMESTAMP, :modUser, SYSTIMESTAMP) ";
    $params = array(
        ":assessmentId" => $assessmentId,
        ":jno" => $jno,
        ":seq" => $seq,
        ":assessmentType" => 'ASMT_REP',
        ":replaceWithFile" => (empty($replaceWithFile)?"N":"Y"),
        ":scheduledMeetingDate" => $scheduledMeetingDate,
        ":startDate" => $startDate,
        ":endDate" => $endDate,
        ":deadline" => $deadline,
        ":regUser" => $user->uno,
        ":modUser" => $user->uno
    );
    if ($db->query($SQL, $params)) {
        $proceed = true;
    }

    if ($proceed) {
        if (empty($replaceWithFile)) {
            $preCnoList = array();
            $SQL  = "SELECT CNO, FUNC_NO ";
            $SQL .= "FROM RISK_APPROVAL_TARGET ";
            $SQL .= "WHERE ASSESSMENT_ID = :assessmentId ";
            $params = array(
                ":assessmentId" => $assessmentId
            );
            $db->query($SQL, $params);
            while($db->next_record()) {
                $row = $db->Record;

                $preCnoList[$row["cno"]][] = $row["func_no"];
            }

            $addCnoList = array();
            if (count($cnoList)) {
                $proceed = false;
                foreach($cnoList as $cno) {
                    $addCnoList[$cno] = $_POST["cno_funcNo_" . $cno];
                    foreach($addCnoList[$cno] as $funcNo) {
                        $SQL  = "MERGE INTO RISK_APPROVAL_TARGET ";
                        $SQL .= "USING DUAL ";
                        $SQL .= "ON (ASSESSMENT_ID = :assessmentId AND APPROVAL_TYPE = :approvalType AND CNO = :cno AND FUNC_NO = :funcNo) ";
                        $SQL .= "WHEN NOT MATCHED THEN ";
                        $SQL .= " INSERT (APPROVAL_TARGET_ID, ASSESSMENT_ID, APPROVAL_TYPE, FUNC_NO, CNO, APPROVAL_STATUS) ";
                        $SQL .= " VALUES (SEQ_APPROVAL_TARGET_ID.NEXTVAL, :assessmentId, :approvalType, :funcNo, :cno, :approvalStatus) ";
                        $params = array(
                            ":assessmentId" => $assessmentId,
                            ":approvalType" => "ASMT_REP",
                            ":cno" => $cno,
                            ":funcNo" => $funcNo,
                            ":approvalStatus" => "00"
                        );
                        if ($db->query($SQL, $params)) {
                            $proceed = true;
                        }
                    }
                }
            }

            if ($proceed) {
                foreach($preCnoList as $cno => $funcList) {
                    if (array_key_exists($cno, $addCnoList)) {
                        foreach($funcList as $funcNo) {
                            if (!in_array($funcNo, $addCnoList[$cno])) {
                                $proceed = false;
                                $SQL  = "DELETE FROM RISK_APPROVAL_TARGET ";
                                $SQL .= "WHERE ASSESSMENT_ID = :assessmentId ";
                                $SQL .= " AND CNO = :cno ";
                                $SQL .= " AND FUNC_NO = :funcNo ";
                                $params = array(
                                    ":assessmentId" => $assessmentId,
                                    ":cno" => $cno,
                                    ":funcNo" => $funcNo
                                );
                                if ($db->query($SQL, $params)) {
                                    $proceed = true;
                                }
                            }
                        }
                    }
                    else {
                        $SQL  = "DELETE FROM RISK_APPROVAL_TARGET ";
                        $SQL .= "WHERE ASSESSMENT_ID = :assessmentId ";
                        $SQL .= " AND CNO = :cno ";
                        $params = array(
                            ":assessmentId" => $assessmentId,
                            ":cno" => $cno
                        );
                        if ($db->query($SQL, $params)) {
                            $proceed = true;
                        }
                    }
                }
            }
        }
        //파일로 대체할 경우
        else {
            $riskFnoList = $_POST["riskFno"];

            //저장된 파일 목록
            $attachFileList = array();
            $SQL  = "SELECT RISK_FNO, FILE_NAME, FILE_LOCATION ";
            $SQL .= "FROM RISK_ASSESSMENT_ATCH ";
            $SQL .= "WHERE ASSESSMENT_ID = :assessmentId ";
            $params = array(
                ":assessmentId" => $assessmentId
            );
            $db->query($SQL, $params);
            while($db->next_record()) {
                $row = $db->Record;

                $attachFileList[$row["risk_fno"]] = array(
                    "fileName" => $row["file_name"],
                    "fileLocation" => $row["file_location"]
                );
            }

            //삭제된 파일
            $delAttachFile = array();
            if (count($riskFnoList) > 0) {
                $delAttachFile = array_diff(array_keys($attachFileList), $riskFnoList);
            }
            else {
                if (count($attachFileList) > 0) {
                    $delAttachFile = array_keys($attachFileList);
                }
            }

            //첨부파일 삭제
            if($delAttachFile > 0) {
                foreach($delAttachFile as $fno) {
                    //물리 삭제
                    unlink($attachFileList[$fno]["fileLocation"]);
                    
                    //데이터베이스 삭제
                    $SQL  = "DELETE FROM RISK_ASSESSMENT_ATCH ";
                    $SQL .= "WHERE RISK_FNO = :riskFno ";
                    $params = array(
                        ":riskFno" => $fno
                    );
                    $db->query($SQL, $params);
        
                    unset($attachFileList[$fno]);
                }
            }

            //존재하는 파일
            $existFileList = array();
            if (count($attachFileList) > 0) {
                foreach($attachFileList as $info) {
                    $existFileList[] = strtolower($info["fileName"]);
                }
            }

            //새로 첨부된 파일
            $fileList = array();
            for ($i=0; $i<count($_FILES['newAttachFile']['name']); $i++) {
                if (!empty($_FILES['newAttachFile']['name'][$i])) {
                    $newFileName = "";
                    $info = pathinfo($_FILES['newAttachFile']['name'][$i]);
                    $oriFileName = $info['basename'];
                    $ext = "." . $info['extension'];
                    $fileName = $info['filename'];
                    //지원하지 않는 특수문자 제거
                    $fileName = iconv("UTF-8", "EUC-KR//TRANSLIT", $fileName);
                    $fileName = iconv("EUC-KR", "UTF-8", $fileName);
                    if(count($attachFileList) > 0) {
                        $tempFileName = $fileName . $ext;
                        //파일 이름이 중복된다면 (n) 번을 붙여서 저장
                        if (in_array(strtolower($tempFileName), $existFileList)) {
                            $j = 1;
                            do {
                                $tempFileName = $fileName . "(" . $j++ . ")" . $ext;
                            } while(in_array(strtolower($tempFileName), $existFileList));
                        }
                        $existFileList[] = strtolower($tempFileName);
                        $newFileName = $tempFileName;
                    } else {
                        $newFileName = $fileName . $ext;
                    }
                    if(!file_exists("../upload/asmt/{$jno}/{$assessmentId}")) {
                        mkdir("../upload/asmt/{$jno}/{$assessmentId}", 0777, true);
                    }
                    $uploadDir = $baseFileDir . "/upload/asmt/{$jno}/{$assessmentId}/" . $newFileName;
                    $uploadFile = $_FILES['newAttachFile']['tmp_name'][$i];
                    
                    if (move_uploaded_file($uploadFile, $uploadDir)) {
                        $fileList[] = array(
                            "fileName" => $oriFileName,
                            "fileLocation" => $uploadDir
                        );
                    }
                }
            }
        
            if (count($fileList) > 0) {
                foreach($fileList as $fileInfo) {
                    $SQL  = "INSERT INTO RISK_ASSESSMENT_ATCH (RISK_FNO, ASSESSMENT_ID, ATCH_TYPE, FILE_NAME, FILE_LOCATION) ";
                    $SQL .= "VALUES (SEQ_RISK_FNO.NEXTVAL, :assessmentId, :atchType, :fileName, :fileLocation) ";
                    $params = array(
                        ":assessmentId" => $assessmentId,
                        ":atchType" => 'ASMT_REP',
                        ":fileName" => $fileInfo["fileName"],
                        ":fileLocation" => $fileInfo["fileLocation"] 
                    );
                    if ($db->query($SQL, $params)) {
                        $proceed = true;
                    }
                }
            }
        }
    }

    if ($proceed) {
        $msg = "저장되었습니다.";
    }
    else {
        $msg = "저장 실패하였습니다.";
    }

    $result = array("test" => $_FILES['newAttachFile']['name'],
    
        "proceed" => $proceed,
        "msg" => $msg
    );

    echo json_encode($result);
}
//상세
else if ("DETAIL" == $mode) {
    $assessmentId = $_POST["assessmentId"];

    $assessmentInfo = array();
    $subConList = array();
    $seqList = array();
    $attachFileList = array();
    if (empty($assessmentId)) {
        $SQL  = "SELECT SEQ, TO_CHAR(END_DATE, 'YYYY-MM-DD') AS END_DATE ";
        $SQL .= "FROM RISK_ASSESSMENT_INFO ";
        $SQL .= "WHERE JNO = :jno ";
        $SQL .= " AND ASSESSMENT_TYPE = 'ASMT_REP' ";
        $SQL .= " AND SEQ = (SELECT NVL(MAX(SEQ), 0) ";
        $SQL .= "  FROM RISK_ASSESSMENT_INFO ";
        $SQL .= "  WHERE JNO = :jno ";
        $SQL .= "   AND ASSESSMENT_TYPE = 'ASMT_REP' ";
        $SQL .= " ) ";
        $params = array(
            ":jno" => $jno
        );
        $db->query($SQL, $params);
        if ($db->nf() > 0) {
            $db->next_record();
            $row = $db->Record;
            $seq = $row["seq"] + 1;
            $startDate = new DateTime($row["end_date"]);
            $startDate = $startDate->modify("+1 day");
        }
        else {
            $seq = 1;
            $startDate = new DateTime();
            $startDate->modify("+1 week");
            $startDate = $startDate->modify("next monday");
        }
        $endDate = clone $startDate;
        $endDate = $endDate->modify("+13 days");
        $scheduledMeetingDate = clone $startDate;
        $scheduledMeetingDate->modify("last friday");
        $deadline = clone $scheduledMeetingDate;
        $deadline = $deadline->modify("-1 day");

        $assessmentInfo = array(
            "seq" => $seq, 
            "replaceWithFile" => "N", 
            "scheduledMeetingDate" => $scheduledMeetingDate->format("Y-m-d"), 
            "startDate" => $startDate->format("Y-m-d"), 
            "endDate" => $endDate->format("Y-m-d"), 
            "deadline" => $deadline->format("Y-m-d") 
        );

        if ($assessmentInfo["seq"] > 1) {
            for ($i = ($assessmentInfo["seq"] - 1); $i > 0; $i--) {
                $seqList[] = $i;
            }
        }
        else {
            $seqList[] = $assessmentInfo["seq"];
        }
    }
    else {
        $SQL  = "SELECT SEQ, REPLACE_WITH_FILE, TO_CHAR(SCHEDULED_MEETING_DATE, 'YYYY-MM-DD') AS SCHEDULED_MEETING_DATE, ";
        $SQL .= " TO_CHAR(START_DATE, 'YYYY-MM-DD') AS START_DATE, TO_CHAR(END_DATE, 'YYYY-MM-DD') AS END_DATE, TO_CHAR(DEADLINE, 'YYYY-MM-DD') AS DEADLINE ";
        $SQL .= "FROM RISK_ASSESSMENT_INFO ";
        $SQL .= "WHERE ASSESSMENT_ID = :assessmentId ";
        $params = array(
            ":assessmentId" => $assessmentId
        );
        $db->query($SQL, $params);
        $db->next_record();
        $row = $db->Record;
    
        $assessmentInfo = array(
            "seq" => $row["seq"], 
            "replaceWithFile" => $row["replace_with_file"], 
            "scheduledMeetingDate" => $row["scheduled_meeting_date"], 
            "startDate" => $row["start_date"], 
            "endDate" => $row["end_date"], 
            "deadline" => $row["deadline"] 
        );

        //파일로 대체
        if ("Y" == $assessmentInfo["replaceWithFile"]) {
            for ($i = ($assessmentInfo["seq"]); $i > 0; $i--) {
                $seqList[] = $i;
            }

            //첨부파일
            $attachFileList = array();
            $SQL  = "SELECT RISK_FNO, FILE_NAME ";
            $SQL .= "FROM RISK_ASSESSMENT_ATCH ";
            $SQL .= "WHERE ASSESSMENT_ID = :assessmentId ";
            $SQL .= "ORDER BY RISK_FNO ";
            $params = array(
                ":assessmentId" => $assessmentId
            );
            $db->query($SQL, $params);
            while($db->next_record()) {
                $row = $db->Record;

                $attachFileList[] = array(
                    "fno" => $row["risk_fno"], 
                    "fileName" => $row["file_name"] 
                );
            }
        }
        else {
            //선택된 공종(현장)
            $SQL  = "SELECT CNO, FUNC_NO ";
            $SQL .= "FROM RISK_APPROVAL_TARGET ";
            $SQL .= "WHERE ASSESSMENT_ID = :assessmentId ";
            $params = array(
                ":assessmentId" => $assessmentId
            );
            $db->query($SQL, $params);
            while($db->next_record()) {
                $row = $db->Record;

                $subConList[$row["cno"]][] = $row["func_no"];
            }
        }
    }

    $result = array(
        "assessmentInfo" => $assessmentInfo,
        "subConList" => $subConList,
        "seqList" => $seqList,
        "attachFileList" => $attachFileList
    );

    echo json_encode($result);
}
else if ("CHANGE_AUTO" == $mode) {
    $chkIsAuto = $_POST["isAuto"];
    $proceed = true;

    if($chkIsAuto == 'true') {
        $isAuto = 'Y';
    } else {
        $isAuto = 'N';
    }

    $SQL = "UPDATE RISK_CODE_SET
            SET VAL3 = :isAuto
            WHERE MAJOR_CD = 'RISK_OPTION'
            AND MINOR_CD = 'RISK_AUTO'
            AND VAL2 = :jno";
    $params = array(
        ":isAuto" => $isAuto,
        ":jno" => $jno
    );
    if($db->query($SQL, $params)) {
        $proceed = true;
    } else {
        $proceed = false;
    }

    $result = array(
        "proceed" => $proceed
    );

    echo json_encode($result);
}
//초기화면 표시
else if ("INIT" == $mode) {
    $isManager = "N";
    $auth = "";
    if (("HEAD" == $_SESSION["risk"]["user_type"]) && isset($_SESSION["risk"]["auth"])) {
        $isManager = "Y";
        $auth = $_SESSION["risk"]["auth"];
    }

    //해당 프로젝트의 협력업체와 공종(현장) 목록
    $subConList = array();
    $SQL  = "SELECT I.CNO, I.COMP_NAME, F.FUNC_NO, C.FUNC_NAME ";
    $SQL .= "FROM JOB_SUBCON_INFO I ";
    $SQL .= " JOIN JOB_SUBCON_FUNC F ON I.CNO = F.CNO AND I.JNO = F.JNO ";
    $SQL .= " JOIN COMMON.COMM_FUNC_QHSE C ON F.FUNC_NO = C.FUNC_NO ";
    $SQL .= "WHERE I.JNO = :jno ";
    $SQL .= "ORDER BY I.CNO, C.SORT_NO ";
    $params = array(
        ":jno" => $jno
    );
    $db->query($SQL, $params);
    while($db->next_record()) {
        $row = $db->Record;

        $subConList[$row["cno"]]["compName"] = $row["comp_name"];
        $subConList[$row["cno"]]["funcList"][] = array(
            "funcNo" => $row["func_no"],
            "funcName" => $row["func_name"]
        );
    }

    // 자동생성 버튼 여부
    $autoRisk = array();
    $SQL = "SELECT VAL3 
            FROM RISK_CODE_SET
            WHERE MAJOR_CD = 'RISK_OPTION'
            AND MINOR_CD = 'RISK_AUTO'
            AND VAL2 = :jno";
    $db->query($SQL, $params);
    $cnt = $db->nf();
    
    if($cnt > 0) {
        $db->next_record();
        $row = $db->Record;

        $autoRisk = array(
            "isOption" => 'Y',
            "isAuto" => $row["val3"]
        );
    } else {
        $autoRisk = array(
            "isOption" => 'N'
        );
    }

    $result = array(
        "isManager" => $isManager,
        "subConList" => $subConList,
        "auth" => $auth,
        "autoRisk" => $autoRisk
    );

    echo json_encode($result);
}

// 수시평가 자동 생성
function autoGenerateRisk() {
    global $db;
    global $jno;

    // $params = array();
    // $SQL = "SELECT TO_CHAR(MAX(END_DATE), 'YYYY-MM-DD') AS END_DATE, MAX(SEQ) AS SEQ 
    //         FROM RISK_ASSESSMENT_INFO 
    //         WHERE ASSESSMENT_TYPE = 'ASMT_REP'
    //         AND JNO = :jno
    //         GROUP BY JNO";
    // $params = array(
    //     ":jno" => $jno
    // );
    // $db->query($SQL, $params);
    // $db->next_record();

    // $row = $db->Record;

    // $endDate = $row["end_date"];
    // $seq = $row["seq"];

    

}

?>
