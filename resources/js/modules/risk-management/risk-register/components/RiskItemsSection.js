import React, {useContext, useEffect, useState} from 'react';
import {AccordionContext} from "react-bootstrap";
import Select from "../../../../common/custom-react-select/CustomReactSelect";
import { useDidMountEffect } from "../../../../custom-hooks";
import RiskItemsTable from "./RiskItemsTable";
import {useSelector,useDispatch} from "react-redux";
import { Inertia } from '@inertiajs/inertia';
import { usePage } from "@inertiajs/inertia-react";
import moment from 'moment/moment';
import ReactTooltip from "react-tooltip";
import { storePerPageData,storeCurrentPageData } from '../../../../store/actions/risk-management/pagedata';


const RiskItemsSection = props => {
    const reduxPageData = useSelector(state => state.riskReducer.pageDataReducer);
    let paramsPerPage = reduxPageData.perPage;
    let paramsCurrentPage = reduxPageData.currentPage;
    const {primaryFilters, categoryId, eventKey, onDeleteCategory, onUpdateCategoryRisksCount,project_id,risksAffectedProperties,setIsLoadingValue,handleUpdateRiskStatus,prevPage="dashboard",dateToFilter} = props;
    const [risks, setRisks] = useState([]);
    const [loading, setLoading] = useState(false);
    const [toggle, setToggle] = useState(false);
    const [firstRender, setFirstRender] = useState(true);
    const [from, setFrom] = useState(0);
    const [to, setTo] = useState(0);
    const [total, setTotal] = useState(0);
    const [pagination, setPagination] = useState({
        current_page: 1,
        total: 1
    });
    const dispatch = useDispatch();
    const { request_url, current_page } = usePage().props;
    const [clickable, setClickable] = useState(true);
    let [paginationCounter, setPaginationCounter] = useState(1);

    const { selectedProjects } = useSelector(
        (store) => store.riskReducer.projectFilterReducer
    );

    const appDataScope = useSelector(state => state.appDataScope.selectedDataScope.value);

    const handleDeleteCategory = id => setRiskCategories(riskCategories.filter(r => r.id !== id));

    const [q, setQ] = useState('');
    const [searchTerm, setSearchTerm] = useState('');
    const [perPage, setPerPage] = useState({perPage:10});
    const [defaultPerPageValue, setDefaultPerPageValue] = useState(paramsPerPage ? {label: paramsPerPage, value: paramsPerPage} : {label:10, value:10})
    // setDefaultPerPageValue(paramsPerPage ? {label: paramsPerPage, value: paramsPerPage} : {label:10, value:10});
    const [filterRisk, setFilterRisk] = useState({"name":"id","order":"ASC"});
    const currentEventKey = useContext(AccordionContext);

    const updateRiskFilter = (name="id",order="ASC") => {
        setFilterRisk({"name":name,"order":order});
        fetchRisks(1,perPage.perPage,name,order);
    }

    const handlePerPageChange = (e) => {
        dispatch(storePerPageData(e.value));
        dispatch(storeCurrentPageData(1));
        setRisks([]);
        setPerPage({perPage:e.value});
        fetchRisks(1,e.value);
    }

    const updateRiskTableRow = (risk,id,inherientScore,residualScore) => {
        // const updateRisks = risks.filter((r,index) => if(r.id == id) => {return index});
        const updateRiskIndex = risks.findIndex( x => x.id === id );
        risks[updateRiskIndex].impact=risk.impact+1;
        risks[updateRiskIndex].likelihood=risk.likelihood+1;
        risks[updateRiskIndex].treatment_options=risk.treatment_options;
        risks[updateRiskIndex].inherent_score=inherientScore;
        risks[updateRiskIndex].residual_score=residualScore;
        if(risk.treatment_options == "Accept"){
            risks[updateRiskIndex].status="Close";
            risks[updateRiskIndex].mapped_controls=[];
        }else if(risk.treatment_options == "Mitigate"){
            risks[updateRiskIndex].status="Open";
        }
        setRisks(risks);
        setToggle(!toggle);
    }

    useEffect(() => {
        if(firstRender){
            setFirstRender(false);
            paramsPerPage = reduxPageData.perPage;
            paramsCurrentPage = reduxPageData.currentPage;
            if(paramsCurrentPage || paramsPerPage){
                fetchRisks(paramsCurrentPage, paramsPerPage);
            } else {
                fetchRisks(current_page);
            }
        }
        else{
            dispatch(storePerPageData(10));
            dispatch(storeCurrentPageData(1));
            fetchRisks(current_page);
        }
        
    }, [selectedProjects]);

    useEffect(() => {
        return Inertia.on('before', e => {
            // check where user is going
            if (!(e.detail.visit.url.href.includes("/risks/projects/") || e.detail.visit.url.href.includes("/show") || e.detail.visit.url.href.includes('risks/risks-register-react/') || e.detail.visit.url.href.includes('/risks-register'))) {
                dispatch(storePerPageData(10));
                dispatch(storeCurrentPageData(1));
            }
        });
    });

    useEffect(() => {
        if(firstRender){
            setFirstRender(false);
            if(paramsCurrentPage || paramsPerPage)
                fetchRisks(paramsCurrentPage, paramsPerPage);
            else 
                fetchRisks(current_page);
        }
        else{
            dispatch(storePerPageData(10));
            dispatch(storeCurrentPageData(1));
            fetchRisks(current_page);
        }

        var filterDate = moment(dateToFilter).format('YYYY-MM-DD');
        if(filterDate != moment(new Date()).format('YYYY-MM-DD'))
            setClickable(false);
        else
            setClickable(true);

        // To collapse the expanded Risk Detail in Risk Register List while selected the past date.
        let counter = paginationCounter+1;
        setPaginationCounter(counter);
    }, [dateToFilter]);

    const fetchRisks = async (page = 1,dataPerPage=10,filterName="",filterOrder="") => {
        const project_ids = prevPage == "dashboard"?selectedProjects:project_id;
        if(project_ids != ""){
            try {
                setLoading(true);
                const {data} = await axiosFetch.get(`risks/risks-register-react/${categoryId}/registered-risks`, {
                    params: {
                        search: searchTerm,
                        only_incomplete: primaryFilters.only_incomplete,
                        search_within_category: q,
                        data_scope: appDataScope,
                        page:page,
                        project_id: project_ids,
                        filterName: filterName != ""?filterName:filterRisk.name,
                        filterOrder: filterOrder != ""?filterOrder:filterRisk.order,
                        perPage: dataPerPage,
                        dateToFilter:dateToFilter
                    }
                });
    
                // if(data.data.length === 0 && q === '') return onDeleteCategory(categoryId);
                // if no search results, keep the previous ones
                setLoading(false);
                if(data.data.length === 0 && risks.length !== 0 && q !== '') return;
                setRisks(data.data);
                setFrom(data.from);
                setTo(data.to);
                setTotal(data.total);
                setPagination({
                    ...pagination,
                    current_page: data.current_page,
                    total: data.last_page
                });
                setIsLoadingValue(false);
            } catch (err) {
            }
        }
        ReactTooltip.rebuild();
    };
    
    useDidMountEffect(() => {
        // if (searchTerm.length < 3 && q !== '') setQ('');
        if (currentEventKey.activeEventKey === eventKey);
        fetchRisks(current_page);
    },[primaryFilters, currentEventKey, q, appDataScope,searchTerm]);

    const handleOnPaginate = (page) => {
        dispatch(storeCurrentPageData(page));
        if(paramsCurrentPage && paramsPerPage){
            fetchRisks(page, paramsPerPage);
        } else {
            fetchRisks(page,perPage.perPage);
        }
        let counter = paginationCounter+1;
        setPaginationCounter(counter);
    }
    const removeRiskFromTable = (riskId) => {
        const offset_incomplete = risks.find(r => r.id === riskId).is_complete ? 0 : 1;
        if (risks.length - 1 <= 0) {
            if (pagination.current_page === 1){
                if(q !== ''){
                    // there might be some risks left that don't
                    // match the query
                    setSearchTerm('');
                }else{
                    return onDeleteCategory(categoryId);
                }
            }else{
                fetchRisks(pagination.current_page - 1);
            }
        } else {
            if (pagination.current_page === pagination.total) {
                // just remove the risk
                // because we're in the last page
                setRisks(risks.filter(r => r.id !== riskId));
            } else {
                //re-fetch the current page
                fetchRisks(pagination.current_page, perPage.perPage);
            }
        }
        // onUpdateCategoryRisksCount(categoryId, 1, offset_incomplete);
    }
    const handleOnDelete = riskId => {
        AlertBox(
            {
              title: "Are you sure that you want to delete the risk?",
              text: "This action is irreversible and any mapped controls will be unmapped.",
              showCancelButton: true,
              confirmButtonColor: '#f1556c',
              confirmButtonText: "OK",
              closeOnConfirm: false,
              iconColor: '#f1556c',
              heightAuto:false,
              icon:'warning'
            },
            function (confirmed) {
              if (confirmed.value) {
                Inertia.post(route('risks.register.risks-delete',riskId),{current_page:pagination.current_page},
                            {
                                onSuccess: () =>{
                                    AlertBox({
                                        title: "Deleted!",
                                        text: "Risk deleted successfully.",
                                        confirmButtonColor: "#b2dd4c",
                                        icon:'success',
                                    });
                                    removeRiskFromTable(riskId);
                                },
                                preserveScroll: true,
                                preserveState: true
                            }
                        );
              } 
            }
          );
       
    }

    return (
        <div>
            <div className="top__text d-sm-flex p-2">
                <div className="row">
                    <Select
                        className={`react-select__indicators w-auto`}
                        value={{value: reduxPageData.perPage,label: reduxPageData.perPage}}
                        id={`frequency-select`}
                        classNamePrefix="react-select"
                        onChange={(e) => handlePerPageChange(e)}
                        options={[
                            {value: '10',label: '10'},
                            {value: '25',label: '25'},
                            {value: '50',label: '50'},
                            {value: '100',label: '100'}]}
                    />
                </div>
                <div className="searchbox animated zoomIn ms-auto">
                    <input type="text" placeholder="Search.." value={searchTerm}
                           onChange={e => setSearchTerm(e.target.value)}
                           className="search" style={{ paddingLeft:"30px" }}/>
                    <i className="fas fa-search" style={{ position:"absolute" }}/>
                </div>
            </div>
            {<RiskItemsTable loading={loading} showRiskAddView={props.showRiskAddView} risks={risks} to={to} from={from} totalItem={total} filterRisks={updateRiskFilter} primaryFilters={primaryFilters} onDelete={handleOnDelete} removeRiskFromTable={removeRiskFromTable} onPaginate={handleOnPaginate}
                                pagination={pagination} handleUpdateRiskStatus={handleUpdateRiskStatus} onUpdateCategoryRisksCount={onUpdateCategoryRisksCount} perPageItem={perPage.perPage} risksAffectedProperties={risksAffectedProperties} updateRiskTableRow={updateRiskTableRow} clickable={clickable} paginateCounter={paginationCounter} />
            }
            <ReactTooltip />
        </div>
    );
};

export default RiskItemsSection;
