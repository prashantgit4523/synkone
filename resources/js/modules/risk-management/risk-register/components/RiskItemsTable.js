import React, { useEffect, useState } from "react";
import { Accordion, Pagination } from "react-bootstrap";
import ContentLoader from "../../../../common/content-loader/ContentLoader";
import RiskItemToggle from "./RiskItemToggle";
import RiskItemDetails from "./RiskItemDetails";
import { paginate } from "../../../../utils/pagination";
import ReactTooltip from "react-tooltip";

const RiskItemsTable = ({
    risks,
    to,
    from,
    totalItem,
    filterRisks,
    onPaginate,
    pagination,
    onDelete,
    removeRiskFromTable,
    primaryFilters,
    onUpdateCategoryRisksCount,
    handleUpdateRiskStatus,
    showRiskAddView,
    perPageItem,
    risksAffectedProperties,
    riskMatrixLikelihoods,
    riskMatrixImpacts,
    loading,
    updateRiskTableRow,
    clickable,
    paginateCounter
}) => {
    const { current_page, total } = pagination;
    const start = (current_page - 1) * perPageItem;
    const [clickData, setClickData] = useState({
        "name":"default",
        "order":"ASC",
    });

    useEffect(() => {
        ReactTooltip.rebuild();
    }, [clickable]);

    function strCompare(str1,str2){
        return str1.toString() == str2.toString();
    }

    const setSortedField = (name) => {
        var order = "ASC";
        if(strCompare(name,clickData.name)){
            if(strCompare(clickData.order,"ASC")){
                order = "DESC";
            }else{
                order = "ASC";
            }
        }
        var data = {
            "name":name,
            "order":order,
        }
        //call api and pass data for filter
        filterRisks(name,order);
        setClickData(data);
    }

    return (
        <div>
            <div className="risk__table border mb-1">
                <Accordion>
                    <table className="table risk-register-table dt-responsive">
                        <thead className="table-light">
                            <tr>
                                <th className="risk__id-width">Risk ID <i className={(clickData.name == "id" && clickData.order == "ASC") ? "float-end fa fa-chevron-up" : (clickData.name == "id" && clickData.order == "DESC") ?"float-end fa fa-chevron-down":""} aria-hidden="true"></i></th>
                                {/* <th className="risk__id-width" onClick={() => setSortedField('id')}>Risk ID <i className={(clickData.name == "id" && clickData.order == "ASC") ? "float-end fa fa-chevron-up" : (clickData.name == "id" && clickData.order == "DESC") ?"float-end fa fa-chevron-down":""} aria-hidden="true"></i></th> */}
                                <th className="curson_thead" onClick={() => setSortedField('title')}>Risk Title <i className={(clickData.name == "title" && clickData.order == "ASC") ? "float-end fa fa-chevron-up" : (clickData.name == "title" && clickData.order == "DESC") ?"float-end fa fa-chevron-down":""} aria-hidden="true"></i></th>
                                <th>Organization</th>
                                <th>Risk Project</th>
                                <th className="curson_thead" onClick={() => setSortedField('category')}>Category <i className={(clickData.name == "category" && clickData.order == "ASC") ? "float-end fa fa-chevron-up" : (clickData.name == "category" && clickData.order == "DESC") ?"float-end fa fa-chevron-down":""} aria-hidden="true"></i></th>
                                <th className="hide-on-sm hide-on-xs curson_thead" onClick={() => setSortedField('control')}>
                                    Control<i className={(clickData.name == "control" && clickData.order == "ASC") ? "float-end fa fa-chevron-up" : (clickData.name == "control" && clickData.order == "DESC") ?"float-end fa fa-chevron-down":""} aria-hidden="true"></i>
                                </th>
                                <th className="hide-on-sm hide-on-xs curson_thead" onClick={() => setSortedField('status')}>
                                    Status<i className={(clickData.name == "status" && clickData.order == "ASC") ? "float-end fa fa-chevron-up" : (clickData.name == "status" && clickData.order == "DESC") ?"float-end fa fa-chevron-down":""} aria-hidden="true"></i>
                                </th>
                                <th className="hide-on-xs hide-on-xs curson_thead" onClick={() => setSortedField('treatment')}>
                                    Treatment Option<i className={(clickData.name == "treatment" && clickData.order == "ASC") ? "float-end fa fa-chevron-up" : (clickData.name == "treatment" && clickData.order == "DESC") ?"float-end fa fa-chevron-down":""} aria-hidden="true"></i>
                                </th>
                                <th className="hide-on-xs curson_thead" onClick={() => setSortedField('likelihood')}>Likelihood<i className={(clickData.name == "likelihood" && clickData.order == "ASC") ? "float-end fa fa-chevron-up" : (clickData.name == "likelihood" && clickData.order == "DESC") ?"float-end fa fa-chevron-down":""} aria-hidden="true"></i></th>
                                <th className="hide-on-xs hide-on-sm curson_thead" onClick={() => setSortedField('impact')}>
                                    Impact<i className={(clickData.name == "impact" && clickData.order == "ASC") ? "float-end fa fa-chevron-up" : (clickData.name == "impact" && clickData.order == "DESC") ?"float-end fa fa-chevron-down":""} aria-hidden="true"></i>
                                </th>
                                <th className="hide-on-xs hide-on-sm curson_thead" onClick={() => setSortedField('inherentScore')}>
                                    Inherent Score<i className={(clickData.name == "inherentScore" && clickData.order == "ASC") ? "float-end fa fa-chevron-up" : (clickData.name == "inherentScore" && clickData.order == "DESC") ?"float-end fa fa-chevron-down":""} aria-hidden="true"></i>
                                </th>
                                <th className="hide-on-sm hide-on-xs curson_thead" onClick={() => setSortedField('residualScore')}>
                                    Residual Score<i className={(clickData.name == "residualScore" && clickData.order == "ASC") ? "float-end fa fa-chevron-up" : (clickData.name == "residualScore" && clickData.order == "DESC") ?"float-end fa fa-chevron-down":""} aria-hidden="true"></i>
                                </th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        { loading ? <tr><td colSpan={11}><div className="row text-lg-center"><div className="col-md-12" style={{ minHeight:"100px",minWidth:"100%",margin:"50px auto"}} ><ContentLoader show={true}></ContentLoader></div></div></td></tr>
                        : (totalItem == 0) ? <tr><td colSpan={11}><center>{"No risks found."}</center></td></tr>:risks.map((risk, index) => {
                                const eventKey = `risk_${risk.id}_${paginateCounter}`;
                                const position = from+index;
                                return (
                                    <React.Fragment key={index+1}>
                                        <RiskItemToggle
                                            position={position}
                                            eventKey={eventKey}
                                            risk={risk}
                                            onDelete={onDelete}
                                            showRiskAddView={showRiskAddView}
                                            clickable={clickable}
                                        />
                                        <tr>
                                            <td
                                                className="p-2"
                                                colSpan="11"
                                                width="100%"
                                            >
                                                <Accordion.Collapse
                                                    eventKey={eventKey}
                                                >
                                                    <RiskItemDetails
                                                        key={Date.now()}
                                                        removeRiskFromTable={
                                                            removeRiskFromTable
                                                        }
                                                        handleUpdateRiskStatus={
                                                            handleUpdateRiskStatus
                                                        }
                                                        onUpdateCategoryRisksCount={
                                                            onUpdateCategoryRisksCount
                                                        }
                                                        primaryFilters={
                                                            primaryFilters
                                                        }
                                                        risk={risk}
                                                        risksAffectedProperties={risksAffectedProperties}
                                                        riskMatrixLikelihoods={riskMatrixLikelihoods}
                                                        riskMatrixImpacts={riskMatrixImpacts}
                                                        updateRiskTableRow={updateRiskTableRow}
                                                        clickable={clickable}
                                                    />
                                                </Accordion.Collapse>
                                            </td>
                                        </tr>
                                    </React.Fragment>
                                );
                            })}
                        </tbody>
                    </table>
                    <ReactTooltip />
                </Accordion>
                <div className="row">
                            {
                            (from > 0 && to > 0 && totalItem > 0 ) &&
                                <div className="col-md-6">
                                    {!loading &&
                                        <div className="text-bold p-3">Showing {from} to {to} of {totalItem} entries</div>
                                    }
                            </div>
                            }
                            <div className="col-md-6">
                                {total > 1 && (
                                <Pagination className="pt-2 float-end pagination-rounded">
                                {
                                <Pagination.Prev
                                    onClick={() => onPaginate(current_page - 1)}
                                    className="paginate_button previous"
                                    disabled={current_page === 1}
                                    children={
                                        <i className="fas fa-chevron-left"></i>
                                    }
                                />
                                }
                                {paginate(current_page, total).map((page, i) =>
                                page !== "..." ? (
                                    <Pagination.Item
                                        key={i}
                                        active={current_page === page}
                                        onClick={() => onPaginate(page)}
                                    >
                                        {page}
                                    </Pagination.Item>
                                ) : (
                                    <Pagination.Ellipsis key={i} />
                                )
                                )}
                                {
                                <Pagination.Next
                                    onClick={() => onPaginate(current_page + 1)}
                                    className="paginate_button next"
                                    disabled={current_page === total}
                                    children={
                                        <i className="fas fa-chevron-right"></i>
                                    }
                                />
                                }
                                </Pagination>
                                )}
                            </div>
                </div>
            </div>
        </div>
    );
};

export default RiskItemsTable;
