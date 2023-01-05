import React, {forwardRef, Fragment, useImperativeHandle, useState} from 'react';
import { MultiSelect } from "react-multi-select-component";
import { useDispatch, useSelector } from 'react-redux';
import { fetchProjectFilterData, updateSelectedProjects } from '../../../../store/actions/global-dashboard/project-filter';
import { useDidMountEffect } from '../../../../custom-hooks';
import './project-filter.css'
import './project-filter.scss'

function ProjectFilter(props, ref) {
    const dispatch = useDispatch()
    const appDataScope = useSelector(state => state.appDataScope.selectedDataScope.value)
    const { selectedDepartment } = useSelector(state => state.commonReducer.departmentFilterReducer)
    const { projects } = useSelector(state => state.globalDashboardReducer.projectFilterReducer)
    const [selected, setSelected] = useState([]);
    const [projectOptions, setProjectOptions] = useState([])

    /* Fires when selectedDepartment updates */
    useDidMountEffect(() => {
        dispatch(fetchProjectFilterData({
            data_scope: appDataScope,
            selected_departments: selectedDepartment.join(),
            filter_date: props.filterDate
        }))
    }, [selectedDepartment])


    /* Setting project filter options */
    useDidMountEffect(() => {
        /* Un-selecting prev-selected before re-render */
        // unSelectAll()

        let data = projects.map((project) => {
            return {
                value: project.id,
                label: project.name
            }
        })

        setProjectOptions(data)
    }, [projects])

    /* when project options are updated on data scope change */
    useDidMountEffect(() => {
        // if project id present in url
        var url_string = window.location.href; //window.location.href
        var url = new URL(url_string);
        var project_f = url.searchParams.get("selected_projects");
        let selected_data=[];
        if(project_f){
            var project_array=project_f.split(',');
        
            selected_data = projectOptions.filter((project) => {
                return project_array.includes(project.value.toString())
            })
        }
        if(selected_data.length>0){
            handleProjectSelect(selected_data);
        }
        else{
            selectAll()
        }
    }, [projectOptions])


    // The component instance will be extended
    // with whatever you return from the callback passed
    // as the second argument
    useImperativeHandle(ref, () => ({
        selectAll
    }));

    /* Handling project select*/
    const handleProjectSelect = (selectedProjects) => {
        /*updating local state*/
        setSelected(selectedProjects)
    }

    /* Selects the all options */
    const selectAll = () => {
        setSelected(projectOptions)
    }

    /* Un-select all*/
    const unSelectAll = () => {

        setSelected([])
    }

    /* updateSelectedProjects in Store */
    useDidMountEffect(() => {
        dispatch(updateSelectedProjects(selected.map(selectedProject => selectedProject.value)))
    },[selected])


    return (
        <Fragment>
            <div id="common-project-filter">
                <MultiSelect
                    options={projectOptions}
                    value={selected}
                    onChange={handleProjectSelect}
                    className={`project-filter-container ${props.className}`}
                    overrideStrings={{
                        allItemsAreSelected: "All projects are selected.",
                        search: "Search project(s)",
                        "selectSomeItems": "Select project(s)",
                    }}
                    labelledBy="Select project(s)"
                />
            </div>
        </Fragment>
    );
}

export default forwardRef(ProjectFilter);
