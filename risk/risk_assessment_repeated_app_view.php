<script type="text/javascript">
var htmlRiskType = "";
var htmlFrequency = "";
var htmlStrength = "";
let authList = {};
$(document).ready(function() {
    $("#mode").val("INIT");
    $("#jno").val('<?php echo $_POST["jno"]; ?>');
    $("#tray").val('<?php echo $_POST["tray"]; ?>');
    $("#assessmentId").val('<?php echo $_POST["assessmentId"]; ?>');
    $("#approvalTargetId").val('<?php echo $_POST["approvalTargetId"]; ?>');
    $.ajax({ 
        type: "POST", 
        url: "risk/risk_assessment_repeated_app.php", 
        data: $("#mainForm").serialize(),
        dataType: "json", 
        success: function(result) {
            //권한
            $("#auth").val(result["auth"]);
            $("#dangerHazardLevel").val(result["dangerHazardLevel"]);

            var info = result["assessmentInfo"];
            $("#tblAssessmentInfo td:eq(0)").html("차수:" + info["seq"]);
            $("#tblAssessmentInfo td:eq(1)").html("구분:" + info["assessmentTypeName"]);
            $("#tblAssessmentInfo td:eq(2)").html("적용기간:" + info["assessmentTerm"]);

            //협력업체
            var html = "";
            $.each(result["subconList"], function(i, list) {
                html += '<option value="' + list["cno"] + '">' + list["compName"] + '</option>';
            });
            if (result["auth"] == "SUPERVISOR") {
                $("#ddlSubcon").append(html);
            }
            else {
                $("#ddlSubcon").append('<option value="">전체</option>' + html);
            }

            //공종(현장)
            if (result["auth"] == "SUPERVISOR") {
                html = "";
                $.each(result["funcNoList"], function(i, list) {
                    html += '<option value="' + list["funcNo"] + '">' + list["funcName"] + '</option>';
                });
                $("#ddlFuncNo").append(html);
            }
            else {
                $("#ddlFuncNo").append('<option value="">전체</option>');
            }

            //권한 목록
            authList = result["authList"];

            //재해 형태
            htmlRiskType = '<select id="ddlRiskType" name="ddlRiskType" style="width: 160px;">';
            $.each(result["riskTypeList"], function(i, list) {
                htmlRiskType += '<option value="' + list["key"] + '">' + list["val"] + '</option>';
            });
            htmlRiskType += '</select>';

            //빈도
            htmlFrequency = '<select id="ddlFrequency" name="ddlFrequency" style="width: 40px;" onchange="calculateRating()">';
            $.each(result["frequencyList"], function(i, list) {
                htmlFrequency += '<option value="' + list["key"] + '">' + list["val"] + '</option>';
            });
            htmlFrequency += '</select>';

            //강도
            htmlStrength = '<select id="ddlStrength" name="ddlStrength" style="width: 40px;" onchange="calculateRating()">';
            $.each(result["strengthList"], function(i, list) {
                htmlStrength += '<option value="' + list["key"] + '">' + list["val"] + '</option>';
            });
            htmlStrength += '</select>';
        },
        complete: function() {
            onDdlFuncNoChange();
        },
        error: function (request, status, error) {
            alert("code:"+request.status+"\n"+"message:"+request.responseText+"\n"+"error:"+error);
        }
    });

    //결재 창
    $('#dialogSignApproval').jqxWindow({
        width: 500,
        height: 300, 
        resizable: true,
        autoOpen: false,
        isModal: true,
        cancelButton: $('#btnCancelSignApproval')
    });
    $('#dialogSignApproval').on('close', function (event) {
        $("#returnReason").val("");
    });
    $('#dialogSignApproval').appendTo($("#mainForm"));
    $("#dialogSignApproval").css("visibility", "visible");

    var thAssessmentList = $('#tblAssessmentList').find('thead th');
    $('#tblAssessmentList').closest('div.tableFixHead').on('scroll', function() {
        thAssessmentList.css('transform', 'translateY('+ this.scrollTop +'px)');
    });

    //협력업체 선택
    $("#ddlSubcon").on("change", onDdlSubconChange);
    //공종(현장) 선택
    $("#ddlFuncNo").on("change", onDdlFuncNoChange);
    //위험요인추가 버튼 클릭
    $("#btnAddAssessmentItem").on("click", onBtnAddAssessmentItemClick);
    // //저장 버튼 클릭
    // $("#btnSaveApproval").on("click", onBtnSaveApprovalClick);
    //반려 버튼 클릭
    $("#btnReturnApproval").on("click", onBtnReturnApprovalClick);
    //결재 버튼 클릭
    $("#btnSignApproval").on("click", onBtnSignApprovalClick);
    //일괄결재 버튼 클릭
    $("#btnBatchSignApproval").on("click", onBtnBatchSignApprovalClick);
    //목록 버튼 클릭
    $("#btnListApproval").on("click", onBtnListApprovalClick);
});

//협력업체 선택
function onDdlSubconChange() {
    $("#mode").val("LIST_BY_SUBCON");
    $("#ddlFuncNo").val("");

    $.ajax({ 
        type: "POST", 
        url: "risk/risk_assessment_repeated_app.php", 
        data: $("#mainForm").serialize(), 
        dataType: "json", 
        success: function (result) {
            if ($("#ddlSubcon").val() == "") {
                $("#ddlFuncNo").empty().append('<option value="">전체</option>');
            }
            else {
                var html = "";
                $.each(result["funcNoList"], function(i, list) {
                    html += '<option value="' + list["funcNo"] + '">' + list["funcName"] + '</option>';
                });
                $("#ddlFuncNo").empty().append(html);
            }

            showApprovalInfo(result["approvalInfo"], result["approvalList"]);

            showAssessmentList(result["assessmentList"]);
        },
        error: function (request, status, error) {
            alert("code:"+request.status+"\n"+"message:"+request.responseText+"\n"+"error:"+error);
        }
    });
}

//공종(현장) 선택
function onDdlFuncNoChange() {
    $("#mode").val("LIST_BY_FUNC");

    $.ajax({ 
        type: "POST", 
        url: "risk/risk_assessment_repeated_app.php", 
        data: $("#mainForm").serialize(), 
        dataType: "json", 
        success: function (result) {
            showApprovalInfo(result["approvalInfo"], result["approvalList"]);

            showAssessmentList(result["assessmentList"]);
        },
        error: function (request, status, error) {
            alert("code:"+request.status+"\n"+"message:"+request.responseText+"\n"+"error:"+error);
        }
    });
}

//결재 정보
function showApprovalInfo(info, list) {
    $(".popover").popover("hide");

    if (info["showButton"]) {
        $("#btnAddAssessmentItem").show();
        // $("#btnSaveApproval").show();
        if ($("#ddlSubcon").val() == "") {
            $("#btnShowBatchSignApproval").show();
            $("#btnShowReturnApproval").hide();
            $("#btnShowSignApproval").hide();
        }
        else {
            $("#btnShowBatchSignApproval").hide();
            $("#btnShowReturnApproval").show();
            $("#btnShowSignApproval").show();
        }
    }
    else {
        $("#btnAddAssessmentItem").hide();
        // $("#btnSaveApproval").hide();
        $("#btnShowBatchSignApproval").hide();
        $("#btnShowReturnApproval").hide();
        $("#btnShowSignApproval").hide();
    }

    //결재 진행상황
    var approvalStatus = [];
    $.each(info["approvalStatusList"], function(k, list) {
        var html = ""
        if ($("#ddlSubcon").val() == "") {
            var func = [];
            $.each(list["funcList"], function (l, funcList){
                func.push(funcList["funcName"]);
            });
            html += '<a href="javascript:void(0);" data-toggle="popover" data-placement="top" data-html="true" data-content="' + func.join("<br />") + '">';
        }
        else {
            //결재 정보 고유번호
            $("#approvalTargetId").val(list["funcList"][0]["approvalTargetId"]);
        }
        html += list["name"];
        if ($("#ddlFuncNo").val() == "") {
            html += '(' + list["funcList"].length + ')';
            html += '</a>';
        }
        approvalStatus.push(html);
    });
    $("#tblAssessmentInfo td:last").html("진행상황:" + approvalStatus.join());
    $('[data-toggle="popover"]').popover();

    var html = "";
    $.each(list, function(i, user) {
        html += '<option value="' + user["uno"] + '"';
        if (user["selected"]) {
            html += ' selected';
        }
        html += '>';
        html += user["userName"];
        html += '</option>';
    });
    $("#approverUno").empty().append(html);
}

//위험성 평가 표시
function showAssessmentList(list) {
    $('#tblAssessmentList').closest('div.tableFixHead').scrollTop(0);
    $("#tblAssessmentList tbody").empty();

    var html = '';
    $.each(list, function(i, info) {
        html += showAssessmentRow(info);
    });
    $("#tblAssessmentList tbody").append(html);

    if ($(".readyToWrite").length > 0) {
        // $("#btnShowReturnApproval").prop("disabled", true);
        $("#btnShowSignApproval").prop("disabled", true);
        $("#btnShowBatchSignApproval").prop("disabled", true);
    }
    else {
        // $("#btnShowReturnApproval").prop("disabled", false);
        $("#btnShowSignApproval").prop("disabled", false);
        $("#btnShowBatchSignApproval").prop("disabled", false);
    }

    //관리감독자일 경우
    if ($("#auth").val() == "SUPERVISOR") {
        //붙여넣기 금지
        $("#tblAssessmentList textarea").on("cut copy paste",function(e) {
            e.preventDefault();
        });
    }
}

function showAssessmentRow(info) {
    var html = '', len = authList.length;
    html += '<tr class="trAssessmentItem_' + info["assessmentItemId"] + '" style="background-color: ' + info["funcColor"] + ';">';
    html += '<td>' + info["funcName"] + '</td>';
    html += '<td rowspan="' + (len + 1) + '">' + info["txtLocation"] + '</td>';
    html += '<td rowspan="' + (len + 1) + '">' + info["txtEquipment"] + '</td>';
    html += '<td colspan="2">' + info["workTypePath"] + '</td>';
    html += '<td>' + info["txtRiskFactor"] + '</td>';
    html += '<td colspan="4">' + info["riskTypeName"] + '</td>';
    html += '<td rowspan="' + (len + 1) + '">' + info["actionDeadline"] + '</td>';
    html += '<td>' + info["subconUserName"] + '</td>';
    html += '<td rowspan="' + (len + 1) + '">';
    if (info["canEdit"]) {
        html += '<button type="button" title="편집" class="btn btn-info btn-sm btn-edit" onclick="onBtnEditAssessmentItemClick(' + info["assessmentItemId"] + ')"><i class="fa-solid fa-pen-to-square"></i></button>';
        html += '<button type="button" title="삭제" class="btn btn-danger btn-sm ml-2 btn-edit" onclick="onBtnDelAssessmentItemClick(' + info["assessmentItemId"] + ')"><i class="fa-solid fa-trash-can"></i></button>';
    }
    html += '</td>';
    html += '</tr>';
    var checkCanSign = true;
    $.each(authList, function(j, auth) {
        var isAuth = ($("#auth").val()).toLowerCase() == auth["code"];
        html += '<tr class="trAssessmentItem_' + info["assessmentItemId"] + '">';
        if (j == 0) {
            html += '<td rowspan="' + len + '"></td>';
        }
        html += '<td>' + auth["name"] + '</td>';
        html += '<td>' + info[auth["code"] + "UserName"] + '</td>';
        html += '<td';
        if (checkCanSign && !(info[auth["code"] + "TxtAction"])) {
            html += ' class="readyToWrite"';
        }
        html += '>';
        if (isAuth && info["canEdit"]) {
            html += '<textarea id="appAction_' + info["assessmentItemId"] + '" name="appAction_' + info["assessmentItemId"] + '" rows="3" style="width: 100%;" ';
            html += ' onfocus="onAppActionFocus(' + info["assessmentItemId"] + ')" onblur="onAppActionBlur(' + info["assessmentItemId"] + ')" >';
            html += info[auth["code"] + "Action"];
            html += '</textarea>';
            // html += '<input type="hidden" id="appAssessmentItemId_' + info["assessmentItemId"] + '" name="appAssessmentItemId[]" value="' + info["assessmentItemId"] + '" />';
        }
        else {
            html += info[auth["code"] + "TxtAction"];
        }
        html += '</td>';
        if (j == 0) {
            //빈도
            html += '<td rowspan="' + len + '">' + info["frequency"] + '</td>';
            //강도
            html += '<td rowspan="' + len + '">' + info["strength"] + '</td>';
            //등급
            html += '<td rowspan="' + len + '"';
            if (info["isDanger"]) {
                html += ' class="isDanger"';
            }
            html += '>';
            html += info["rating"];
            html += '</td>';
            //회의대상
            html += '<td rowspan="' + len + '">';
            if (info["canEdit"]) {
                html += '<input type="checkbox" id="appIsCheck_' + info["assessmentItemId"] + '" name="appIsCheck_' + info["assessmentItemId"] + '" value="Y"';
                if (info["isCheck"] == "Y") {
                    html += ' checked';
                }
                html += ' onclick="onAppIsCheckClick(' + info["assessmentItemId"] + ')">';
            }
            else {
                html += info["isCheck"];
            }
            html += '</td>';
            //조치자
            html += '<td rowspan="' + len + '">' + info["approverName"] + '</td>';
        }
        html += '</tr>';
        if (isAuth) {
            checkCanSign = false;
        }
    });

    return html;
}

function onAppActionFocus(id) {
    var elem = $("#appAction_" + id);
    elem.data('oldVal', elem.val());
}

function onAppActionBlur(id) {
    //대책 변경 시 저장
    if ($("#appAction_" + id).data("oldVal") != $("#appAction_" + id).val()) {
        $("#assessmentItemId").val(id);
        $("#mode").val("SAVE_APP_ACTION");
        $.ajax({ 
            type: "POST", 
            url: "risk/risk_assessment_repeated_app.php", 
            data: $("#mainForm").serialize(),
            dataType: "json", 
            success: function(result) {
                $(".trAssessmentItem_" + id).addClass("trEdit");
                var info = result["assessmentItemInfo"];
                var row = showAssessmentRow(info);

                $(".trEdit:first").before(row);
                $(".trEdit").remove();

                // $("#divResultMsg").empty().html(result["msg"]).fadeIn();
                // $("#divResultMsg").delay(5000).fadeOut();

                if ($(".readyToWrite").length > 0) {
                    // $("#btnShowReturnApproval").prop("disabled", true);
                    $("#btnShowSignApproval").prop("disabled", true);
                    $("#btnShowBatchSignApproval").prop("disabled", true);
                }
                else {
                    // $("#btnShowReturnApproval").prop("disabled", false);
                    $("#btnShowSignApproval").prop("disabled", false);
                    $("#btnShowBatchSignApproval").prop("disabled", false);
                }
            },
            error: function (request, status, error) {
                alert("code:"+request.status+"\n"+"message:"+request.responseText+"\n"+"error:"+error);
            }
        });
    }
}

//회의대상 체크
function onAppIsCheckClick(id) {
    $("#assessmentItemId").val(id);
    $("#mode").val("SAVE_TARGET");
    $.ajax({ 
        type: "POST", 
        url: "risk/risk_assessment_repeated_app.php", 
        data: $("#mainForm").serialize(),
        dataType: "json", 
        success: function(result) {
            // $("#divResultMsg").empty().html(result["msg"]).fadeIn();
            // $("#divResultMsg").delay(5000).fadeOut();
        },
        error: function (request, status, error) {
            alert("code:"+request.status+"\n"+"message:"+request.responseText+"\n"+"error:"+error);
        }
    });
}

// //저장 버튼 클릭
// function onBtnSaveApprovalClick() {
//     $("#mode").val("SAVE_APP_ACTION");
//     $.ajax({ 
//         type: "POST", 
//         url: "risk/risk_assessment_repeated_app.php", 
//         data: $("#mainForm").serialize(),
//         dataType: "json", 
//         success: function(result) {
//             showAssessmentList(result["assessmentList"]);

//             $("#divResultMsg").empty().html(result["msg"]).fadeIn();
//             $("#divResultMsg").delay(5000).fadeOut();
//         },
//         error: function (request, status, error) {
//             alert("code:"+request.status+"\n"+"message:"+request.responseText+"\n"+"error:"+error);
//         }
//     });
// }

//위험성 평가 항목 편집
function onBtnEditAssessmentItemClick(id) {
    $("#assessmentItemId").val(id);
    $("#mode").val("DETAIL");

    $.ajax({ 
        type: "POST", 
        url: "risk/risk_assessment_repeated_app.php", 
        data: $("#mainForm").serialize(), 
        dataType: "json", 
        success: function (result) {
            $(".btn-edit").prop("disabled", true);
            $("#btnShowReturnApproval").prop("disabled", true);
            $("#btnShowSignApproval").prop("disabled", true);
            $("#btnShowBatchSignApproval").prop("disabled", true);

        var info = result["assessmentItemInfo"];
            var len = 0;
            $.each(authList, function(i, auth) {
                if (($("#auth").val()).toLowerCase() == auth["code"]) {
                    len = i + 1;
                    return false;
                }
            });

            var html = '', rules = [];
            html += '<tr class="trEdit">';
            html += '<td>' + info["funcName"] + '</td>';
            //장소/위치
            html += '<td rowspan="' + (len + 1) + '"><textarea id="location" name="location" rows="6" style="width: 100%;">' + info["location"] + '</textarea></td>';
            //사용장비/공구
            html += '<td rowspan="' + (len + 1) + '"><textarea type="text" id="equipment" name="equipment" rows="6" style="width: 100%;">' + info["equipment"] + '</textarea></td>';
            //작업단위
            html += '<td colspan="2">' + info["workTypePath"] + '</td>';
            //위험요인
            html += '<td>';
            html += '<textarea id="riskFactor" name="riskFactor" rows="2" ';
            if (info["riskFactorId"]) {
                html += 'style="width: 100%;background-color: #e9ecef;" readonly';
            }
            else {
                html += 'style="width: 100%;"';
            }
            html += '>';
            html += info["riskFactor"];
            html += '</textarea>';
            html += '</td>';
            //재해형태
            html += '<td colspan="4">' + htmlRiskType + '</td>';
            //조치 기한
            html += '<td rowspan="' + (len + 1) + '"><input type="text" id="actionDeadline" name="actionDeadline" style="width: 100%;" value="' + info["actionDeadline"] + '" /></td>';
            //조치자
            html += '<td><input type="text" id="subconUserName" name="subconUserName" style="width: 100%;" value="' + info["subconUserName"] + '" /></td>';
            html += '<td rowspan="' + (len + 1) + '">';
            html += '<button type="button" title="저장" class="btn btn-info btn-sm" onclick="onBtnSaveAssessmentItemClick()"><i class="fa-solid fa-floppy-disk"></i></button>';
            html += '<button type="button" title="취소" class="btn btn-warning btn-sm ml-2" onclick="onBtnCancelAssessmentItemClick()"><i class="fa-solid fa-arrow-rotate-left"></i></button>';
            html += '</td>';
            var userList = result["userList"];
            $.each(authList, function(i, auth) {
                html += '<tr class="trEdit">';
                if (i == 0) {
                    html += '<td rowspan="' + len + '"></td>';
                }
                html += '<td>' + auth["name"] + '</td>';
                html += '<td>';
                //협력업체
                if (auth["code"] == "subcontractor") {
                    html += info[auth["code"] + "UserName"];
                    html += '<input type="hidden" id="' + auth["code"] + 'Uno" name="' + auth["code"] + 'Uno" value="' + info[auth["code"] + "Uno"] + '" />'
                }
                else {
                    html += '<select id="' + auth["code"] + 'Uno" name="' + auth["code"] + 'Uno"'
                    html += '>';
                    $.each(userList[auth["code"]], function(i, user) {
                        html += '<option value="' + user["uno"] + '"';
                        if (info[auth["code"] + "Uno"] == user["uno"]) {
                            html += ' selected';
                        }
                        html += '>';
                        html += user["userName"];
                        html += '</option>';
                    });
                    html += '</select>';
                }
                html += '</td>';
                html += '<td>';
                html += '<textarea id="' + auth["code"] + 'Action" name="' + auth["code"] + 'Action" rows="4" style="width: 100%;">';
                html += info[auth["code"] + "Action"];
                html += '</textarea>';
                html += '</td>';
                if (i == 0) {
                    //빈도
                    html += '<td rowspan="' + len + '">' + htmlFrequency + '</td>';
                    //강도
                    html += '<td rowspan="' + len + '">' + htmlStrength + '</td>';
                    //등급
                    html += '<td id="tdRating" rowspan="' + len + '"></td>';
                    //회의대상
                    html += '<td rowspan="' + len + '"><input type="checkbox" id="isCheck" name="isCheck" value="Y" /></td>';
                    //확인자
                    html += '<td rowspan="' + len + '">' + info["approverName"] + '</td>';
                    rules.push(
                        { input: '#ddlFrequency', message: '빈도는 필수 입력입니다.', action: 'change'
                            , rule: function (input, commit) {
                                return (input.val() != "" && input.val() != null);
                            } 
                        }
                    );
                    rules.push(
                        { input: '#ddlStrength', message: '강도는 필수 입력입니다.', action: 'change'
                            , rule: function (input, commit) {
                                return (input.val() != "" && input.val() != null);
                            } 
                        }
                    );
                }
                html += '</tr>';

                rules.push(
                    { input: '#' + auth["code"] + 'Action', message: '대책은 필수 입력입니다.', action: 'blur'
                        , rule: function (input, commit) {
                            if ($('#' + auth["code"] + 'Action').length) {
                                return (input.val() != "" && input.val() != null);
                            }
                            else {
                                return true;
                            }
                        } 
                    }
                );

                if (($("#auth").val()).toLowerCase() == auth["code"]) {
                    return false;
                }
            });
            html += '</tr>';

            $(".trAssessmentItem_" + id).last().after(html);

            // initialize validator.
            $('#mainForm').jqxValidator({
                hintType: "label",
                rules: rules
            });

            $("#ddlRiskType").val(info["riskType"]);
            $("#ddlFrequency").val(info["frequency"]);
            $("#ddlStrength").val(info["strength"]);
            calculateRating();

            $(".trAssessmentItem_" + id).hide();
        },
        error: function (request, status, error) {
            alert("code:"+request.status+"\n"+"message:"+request.responseText+"\n"+"error:"+error);
        }
    });
}

//등급 계산
function calculateRating() {
    var frequency = $("#ddlFrequency").val();
    var strength = $("#ddlStrength").val();
    var rating = Number(frequency) * Number(strength);
    $("#tdRating").text(rating);
    $("#isCheck").prop("checked", (rating >= $("#dangerHazardLevel").val()));
}

//저장 버튼 클릭
function onBtnSaveAssessmentItemClick() {
    var valid = $('#mainForm').jqxValidator('validate');
    if (valid) {
        $("#mode").val("SAVE");
        $.ajax({ 
            type: "POST", 
            url: "risk/risk_assessment_repeated_app.php", 
            data: $("#mainForm").serialize(), 
            dataType: "json", 
            success: function (result) {
                var info = result["assessmentItemInfo"];
                var row = showAssessmentRow(info);

                $(".trAssessmentItem_" + info["assessmentItemId"]).remove();
                $(".trEdit:first").before(row);

                onBtnCancelAssessmentItemClick();

                $("#btnShowReturnApproval").prop("disabled", false);
                if ($(".readyToWrite").length > 0) {
                    // $("#btnShowReturnApproval").prop("disabled", true);
                    $("#btnShowSignApproval").prop("disabled", true);
                    $("#btnShowBatchSignApproval").prop("disabled", true);
                }
                else {
                    // $("#btnShowReturnApproval").prop("disabled", false);
                    $("#btnShowSignApproval").prop("disabled", false);
                    $("#btnShowBatchSignApproval").prop("disabled", false);
                }

                // $("#divResultMsg").empty().html(result["msg"]).fadeIn();
                // $("#divResultMsg").delay(5000).fadeOut();
            },
            error: function (request, status, error) {
                alert("code:"+request.status+"\n"+"message:"+request.responseText+"\n"+"error:"+error);
            }
        });
    }
}

//삭제 버튼 클릭
function onBtnDelAssessmentItemClick(id) {
    var isYes = confirm("해당 위험성 평가 항목을 삭제하시겠습니까?")
    if (!isYes) {
        return false;
    }

    $("#assessmentItemId").val(id);
    $("#mode").val("DEL");
    $.ajax({ 
        type: "POST", 
        url: "risk/risk_assessment_repeated_app.php", 
        data: $("#mainForm").serialize(), 
        dataType: "json", 
        success: function (result) {
            if (result["proceed"]) {
                $(".trAssessmentItem_" + id).remove();
            }

            // $("#divResultMsg").empty().html(result["msg"]).fadeIn();
            // $("#divResultMsg").delay(5000).fadeOut();
            if ($(".readyToWrite").length > 0) {
                // $("#btnShowReturnApproval").prop("disabled", true);
                $("#btnShowSignApproval").prop("disabled", true);
                $("#btnShowBatchSignApproval").prop("disabled", true);
            }
            else {
                // $("#btnShowReturnApproval").prop("disabled", false);
                $("#btnShowSignApproval").prop("disabled", false);
                $("#btnShowBatchSignApproval").prop("disabled", false);
            }
        },
        error: function (request, status, error) {
            alert("code:"+request.status+"\n"+"message:"+request.responseText+"\n"+"error:"+error);
        }
    });
}

//편집 취소
function onBtnCancelAssessmentItemClick() {
    $(".trAssessmentItem_" + $("#assessmentItemId").val()).show();

    $(".trEdit").remove();
    $(".btn-edit").prop("disabled", false);
    $("#btnShowReturnApproval").prop("disabled", false);
    if ($(".readyToWrite").length > 0) {
        $("#btnShowSignApproval").prop("disabled", true);
        $("#btnShowBatchSignApproval").prop("disabled", true);
    }
    else {
        $("#btnShowSignApproval").prop("disabled", false);
        $("#btnShowBatchSignApproval").prop("disabled", false);
    }
}

//위험요인 추가 버튼 클릭
function onBtnAddAssessmentItemClick() {
    // show the popup window.
    $("#dialogRiskFactorList").jqxWindow('open');
}

//반영버튼 클릭
function onBtnApplyRiskFactorClick() {
    if (onValidateRiskFactor()) {
        $("#mode").val("SAVE_RISK_FACTOR");
        $.ajax({ 
            type: "POST", 
            url: "risk/risk_assessment_repeated_app.php", 
            data: $("#mainForm").serialize(),
            dataType: "json", 
            success: function(result) {
                showAssessmentList(result["assessmentList"]);

                $("#dialogRiskFactorList").jqxWindow('close');

                // $("#divResultMsg").empty().html(result["msg"]).fadeIn();
                // $("#divResultMsg").delay(5000).fadeOut();
            },
            error: function (request, status, error) {
                alert("code:"+request.status+"\n"+"message:"+request.responseText+"\n"+"error:"+error);
            }
        });
    }
}

//결재 창 보기
function showDialogSign(type) {
    var title = "", msg = "";
    if (type == "return") {
        title = "반려";
        msg = "[" + $("#ddlSubcon option:selected").text() + "] 공종(현장) [" + $("#ddlFuncNo option:selected").text() + "]을(를) 반려하시겠습니까?";
        $("#btnReturnApproval").show();
        $("#btnSignApproval").hide();
        $("#btnBatchSignApproval").hide();
        $("#returnReason").closest("div").show();
    }
    else if (type == "sign") {
        title = "결재";
        msg = "[" + $("#ddlSubcon option:selected").text() + "] 공종(현장) [" + $("#ddlFuncNo option:selected").text() + "]을(를) 결재하시겠습니까?";
        $("#btnReturnApproval").hide();
        $("#btnSignApproval").show();
        $("#btnBatchSignApproval").hide();
        $("#returnReason").closest("div").hide();
    }
    else if (type == "batchSign") {
        title = "일괄결재";
        msg = "일괄 결재하시겠습니까?";
        $("#btnReturnApproval").hide();
        $("#btnSignApproval").hide();
        $("#btnBatchSignApproval").show();
        $("#returnReason").closest("div").hide();
    }
    $("#dialogSignApprovalHeader").text(title);
    $("#msgSign").html(msg);

    $("#dialogSignApproval").jqxWindow('open');
}

//반려 버튼 클릭
function onBtnReturnApprovalClick() {
    $("#mode").val("RETURN");
    $.ajax({ 
        type: "POST", 
        url: "risk/risk_assessment_repeated_app.php", 
        data: $("#mainForm").serialize(),
        dataType: "json", 
        success: function(result) {
            // if (result["proceed"]) {
            //     $("#btnAddAssessmentItem").hide();
            //     $("#btnSubmitAssessmentItem").hide();
            // }

            $("#dialogSignApproval").jqxWindow('close');

            $("#divResultMsg").empty().html(result["msg"]).fadeIn();
            $("#divResultMsg").delay(5000).fadeOut();
        },
        complete: function() {
            onDdlFuncNoChange();
        },
        error: function (request, status, error) {
            alert("code:"+request.status+"\n"+"message:"+request.responseText+"\n"+"error:"+error);
        }
    });
}

//결재 버튼 클릭
function onBtnSignApprovalClick() {
    $("#mode").val("SIGN");
    $.ajax({ 
        type: "POST", 
        url: "risk/risk_assessment_repeated_app.php", 
        data: $("#mainForm").serialize(),
        dataType: "json", 
        success: function(result) {
            // if (result["proceed"]) {
            //     $("#btnAddAssessmentItem").hide();
            //     $("#btnSubmitAssessmentItem").hide();
            // }

            $("#dialogSignApproval").jqxWindow('close');

            $("#divResultMsg").empty().html(result["msg"]).fadeIn();
            $("#divResultMsg").delay(5000).fadeOut();
        },
        complete: function() {
            onDdlFuncNoChange();
        },
        error: function (request, status, error) {
            alert("code:"+request.status+"\n"+"message:"+request.responseText+"\n"+"error:"+error);
        }
    });
}

//일괄결재 버튼 클릭
function onBtnBatchSignApprovalClick() {
    $("#mode").val("BATCH_SIGN");
    $.ajax({ 
        type: "POST", 
        url: "risk/risk_assessment_repeated_app.php", 
        data: $("#mainForm").serialize(),
        dataType: "json", 
        success: function(result) {
            // if (result["proceed"]) {
            //     $("#btnAddAssessmentItem").hide();
            //     $("#btnSubmitAssessmentItem").hide();
            // }

            $("#dialogSignApproval").jqxWindow('close');

            $("#divResultMsg").empty().html(result["msg"]).fadeIn();
            $("#divResultMsg").delay(5000).fadeOut();
        },
        complete: function() {
            onDdlFuncNoChange();
        },
        error: function (request, status, error) {
            alert("code:"+request.status+"\n"+"message:"+request.responseText+"\n"+"error:"+error);
        }
    });
}

//상세 버튼 클릭
function onBtnListApprovalClick() {
    var page = sessionStorage.getItem("prePage");
    if(!page) {
        page = "approval";
    }

    //상세 화면으로 이동
    $("<input>").attr({
        type: "hidden",
        id: "page_id",
        name: "page_id",
        value : page
    }).appendTo( $("#mainForm") );
    $("#mainForm").attr({action:"index.php", method:"post", target:"_self"}).submit();
}

//JOB 선택
function jobSelected() {
    var page = sessionStorage.getItem("prePage");
    if(!page) {
        page = "approval";
    }

    //전자결재현황 목록 화면으로 이동
    $("<input>").attr({
        type: "hidden",
        id: "page_id",
        name: "page_id",
        value : page
    }).appendTo( $("#mainForm") );
    $("#mainForm").attr({action:"index.php", method:"post", target:"_self"}).submit();
}
</script>

<div class="menu-sticky-top">
<ol class="breadcrumb">
    <li class="breadcrumb-item">안전보건 위험성평가</li>
    <li class="breadcrumb-item">위험성 평가 최초/ 정기평가 결재</li>
</ol>
<div class="btnList">
    <button type="button" class="btn btn-primary mr-2" id="btnAddAssessmentItem" style="display: none;"><i class="fa-regular fa-plus"></i>&nbsp;위험요인추가</button>
    <!-- <button type="button" class="btn btn-primary mr-2" id="btnSaveApproval" style="display: none;"><i class="fa-solid fa-floppy-disk"></i>&nbsp;저장</button> -->
    <button type="button" class="btn btn-primary mr-2" id="btnShowReturnApproval" onclick="showDialogSign('return')" style="display: none;"><i class="fa-solid fa-file-arrow-down"></i>&nbsp;반려</button>
    <button type="button" class="btn btn-primary mr-2" id="btnShowSignApproval" onclick="showDialogSign('sign')" style="display: none;"><i class="fa-solid fa-file-arrow-up"></i>&nbsp;결재</button>
    <button type="button" class="btn btn-primary mr-2" id="btnShowBatchSignApproval" onclick="showDialogSign('batchSign')" style="display: none;"><i class="fa-solid fa-file-signature"></i>&nbsp;일괄결재</button>
    <button type="button" class="btn btn-primary mr-2" id="btnListApproval"><i class="fa-solid fa-rotate-left"></i>&nbsp;목록</button>
</div>
</div>

<div class="container-fluid">
<form id="mainForm" name="mainForm">

<table id="tblAssessmentInfo" class="table table-borderless border table-title">
    <tbody>
        <tr>
            <td style="width: 5%;"></td>
            <td style="width:15%;"></td>
            <td style="width:20%;"></td>
            <td style="width:20%;">
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text">협력업체</span>
                    </div>
                    <select id="ddlSubcon" name="ddlSubcon">
                    </select>
                </div>
            </td>
            <td style="width:20%;">
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text">공종(현장)</span>
                    </div>
                    <select id="ddlFuncNo" name="ddlFuncNo">
                    </select>
                </div>
            </td>
            <td style="width:20%;"></td>
        </tr>
    </tbody>
</table>
<div id="divResultMsg" class="alert alert-primary" style="display: none;"></div>

<div class="tableFixHead">
<table id="tblAssessmentList" class="table table-bordered table-sm">
    <thead class="thead-light">
        <tr>
            <th rowspan="2" style="width: 100px;">공종(현장)명</th>
            <th rowspan="2" style="width: 150px;">장소/위치</th>
            <th rowspan="2" style="width: 150px;">사용장비/도구</th>
            <th colspan="2" style="width: 300px;">작업단위</th>
            <th>위험요인</th>
            <th colspan="4" style="width: 190px;">재해형태</th>
            <th rowspan="2" style="width: 120px;">조치기한</th>
            <th style="width: 100px;">조치자</th>
            <th rowspan="2" style="width:  80px;">편집</th>
        </tr>
        <tr>
            <th style="width: 150px;">담당</th>
            <th style="width: 150px;">작성/검토</th>
            <th>안전보건관리대책</th>
            <th style="width:  40px;">빈도</th>
            <th style="width:  40px;">강도</th>
            <th style="width:  40px;">등급</th>
            <th style="width:  70px;">회의대상</th>
            <th>확인자</th>
        </tr>
    </thead>
    <tbody></tbody>
</table>
</div>

<div id="dialogSignApproval" style="visibility: hidden;height: 0;">
    <div id="dialogSignApprovalHeader">
    </div>
    <div id="dialogSignApprovalContents">
        <select id="approverUno" name="approverUno" >
        </select >님
        <p id="msgSign"></p>
        <div class="form-group">
            <label for="returnReason">반려사유</label>
            <textarea id="returnReason" name="returnReason" rows="5" style="width: 100%;"></textarea>
        </div>
        <br />
        <div class="d-flex justify-content-around">
            <button type="button" id="btnReturnApproval" class="btn btn-primary mr-2"><i class="fa-solid fa-file-arrow-down"></i>&nbsp;반려</button>
            <button type="button" id="btnSignApproval" class="btn btn-primary mr-2"><i class="fa-solid fa-file-arrow-up"></i>&nbsp;결재</button>
            <button type="button" id="btnBatchSignApproval" class="btn btn-primary mr-2"><i class="fa-solid fa-file-signature"></i>&nbsp;일괄결재</button>
            <button type="button" id="btnCancelSignApproval" class="btn btn-secondary" >닫기</button>
        </div>
    </div>
</div>

<?php 
//위험요인 추가
require_once 'risk_assessment_risk_factor_view.php';
?>

<input type="hidden" id="mode" name="mode" />
<input type="hidden" id="jno" name="jno" />
<input type="hidden" id="tray" name="tray" />
<input type="hidden" id="assessmentId" name="assessmentId" />
<input type="hidden" id="approvalTargetId" name="approvalTargetId" />
<input type="hidden" id="auth" name="auth" />
<input type="hidden" id="dangerHazardLevel" name="dangerHazardLevel" />
<input type="hidden" id="assessmentItemId" name="assessmentItemId" />
</form>
</div>
